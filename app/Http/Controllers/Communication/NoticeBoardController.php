<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Communication\Notice;
use App\Services\Communication\NoticeAudience;
use App\Services\Communication\NoticeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Notice board for everyone signed in to the branch: only live notices of their audience. Opening one records a read receipt. */
class NoticeBoardController extends Controller
{
    public function __construct(protected NoticeService $notices, protected NoticeAudience $audience) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $notices = $this->notices->boardFor($user, $request->input('category'));

        return view('communication.board', [
            'notices' => $notices,
            'read' => \App\Models\Communication\NoticeRead::query()->where('user_id', $user->id)->whereIn('notice_id', $notices->pluck('id'))->pluck('notice_id')->flip(),
            'layout' => $user->portal() === 'admin' ? 'layouts.branch' : 'layouts.portal',
        ]);
    }

    public function show(Request $request, Notice $notice): View
    {
        $user = $request->user();
        abort_unless($this->audience->canView($notice, $user), 404);
        if ($notice->isLive()) {
            $this->notices->markRead($notice, $user);
        }

        return view('communication.notice', [
            'notice' => $notice,
            'layout' => $user->portal() === 'admin' ? 'layouts.branch' : 'layouts.portal',
        ]);
    }

    public function attachment(Request $request, Notice $notice): StreamedResponse
    {
        abort_unless($this->audience->canView($notice, $request->user()), 404);
        abort_unless($notice->attachment_path && Storage::disk('local')->exists($notice->attachment_path), 404);

        return Storage::disk('local')->download($notice->attachment_path, $notice->attachment_name);
    }
}
