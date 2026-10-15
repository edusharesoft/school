<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Communication\Notice;
use App\Services\Communication\NoticeService;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Institution-wide notices written once in the central area and copied into each chosen branch
 * (every copy shares a group_uuid). Each branch then shows, distributes and archives its copy like its own notices.
 */
class InstitutionNoticeController extends Controller
{
    public function index(DashboardService $dashboard): View
    {
        $this->authorize('notices.view');
        $copies = Notice::query()->whereNotNull('group_uuid')->whereIn('branch_id', $dashboard->visibleBranchIds())->withCount('reads')
            ->with('branch:id,name,code')->latest('id')->limit(600)->get();

        return view('central.notices.index', [
            'groups' => $copies->groupBy('group_uuid')->map(fn ($g) => ['notice' => $g->first(), 'copies' => $g])->take(60),
        ]);
    }

    public function create(DashboardService $dashboard): View
    {
        $this->authorize('notices.create');

        return view('central.notices.create', [
            'branches' => Branch::query()->whereIn('id', $dashboard->visibleBranchIds())->ordered()->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request, DashboardService $dashboard, NoticeService $notices): RedirectResponse
    {
        $this->authorize('notices.create');
        $visible = $dashboard->visibleBranchIds();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:10000'],
            'category' => ['required', Rule::in(Notice::CATEGORIES)],
            'audience' => ['required', Rule::in(['all', 'staff', 'teachers', 'students', 'guardians'])],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer', Rule::in($visible)],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['sms', 'email', 'whatsapp'])],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],
        ]);
        $user = $request->user();
        $publish = $request->boolean('publish_now') && $user->can('notices.publish');
        if (! $user->can('notifications.send')) {
            $data['channels'] = [];
        }
        $data += ['is_emergency' => $request->boolean('is_emergency'), 'is_pinned' => $request->boolean('is_pinned'), 'is_public' => false];
        $group = (string) Str::uuid();
        $upload = $request->file('attachment');
        $done = 0;

        foreach (Branch::query()->with('institution')->whereIn('id', $data['branch_ids'])->get() as $branch) {
            tenant()->runForBranch($branch, function () use ($data, $upload, $user, $group, $publish, $notices, &$done) {
                $notice = $notices->save($data, null, $user);
                if ($upload) {
                    // Each branch keeps its own copy of the file under its storage folder.
                    $path = tenant()->branch()->storagePath('notices').'/'.Str::random(32).'.'.$upload->getClientOriginalExtension();
                    Storage::disk('local')->put($path, file_get_contents($upload->getRealPath()));
                    $notice->forceFill(['attachment_path' => $path, 'attachment_name' => mb_substr($upload->getClientOriginalName(), 0, 191)]);
                }
                $notice->forceFill(['group_uuid' => $group])->save();
                if ($publish) {
                    $notices->publish($notice, $user);
                }
                $done++;
            });
        }

        return redirect()->route('central.notices.index')->with('success', trans_choice('Notice sent to :count branch.|Notice sent to :count branches.', $done, ['count' => $done]));
    }
}
