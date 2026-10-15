<?php

namespace App\Http\Controllers\Branch\Communication;

use App\Http\Controllers\Controller;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Communication\Notice;
use App\Models\Students\Student;
use App\Services\Communication\NoticeService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Back office: write, schedule, publish, archive notices and see who read them. */
class NoticeController extends Controller
{
    public function __construct(protected NoticeService $notices) {}

    public function index(Request $request): View
    {
        $this->authorize('notices.view');
        $status = $request->input('status');

        return view('branch.communication.notices.index', [
            'notices' => Notice::query()->with('creator:id,name')->withCount('reads')
                ->when($status === 'live', fn ($q) => $q->live())
                ->when($status === 'expired', fn ($q) => $q->where('status', 'published')->where('expires_at', '<=', now()))
                ->when(in_array($status, ['draft', 'scheduled', 'archived'], true), fn ($q) => $q->where('status', $status))
                ->when($request->input('category'), fn ($q, $c) => $q->where('category', $c))
                ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where('title', 'like', "%$t%"))
                ->orderByDesc('is_emergency')->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END")
                ->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('notices.create');

        return view('branch.communication.notices.form', $this->formData(new Notice(['audience' => 'all', 'category' => 'general'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('notices.create');
        $notice = $this->notices->save($this->validated($request), $request->file('attachment'), $request->user());
        if ($request->boolean('publish_now') && $request->user()->can('notices.publish')) {
            $this->notices->publish($notice, $request->user());

            return redirect()->route('branch.communication.notices.show', $notice)->with('success', $notice->status === 'scheduled' ? __('Notice scheduled.') : __('Notice published.'));
        }

        return redirect()->route('branch.communication.notices.show', $notice)->with('success', __('Notice saved as a draft.'));
    }

    public function show(Notice $notice): View
    {
        $this->authorize('notices.view');

        return view('branch.communication.notices.show', [
            'notice' => $notice->load(['creator:id,name', 'publisher:id,name']),
            'stats' => $notice->status === 'published' ? $this->notices->readStats($notice) : null,
            'readers' => $notice->reads()->with('user:id,name,user_type')->latest('read_at')->limit(100)->get(),
            'delivery' => $notice->distributed_at ? \App\Models\Communication\NotificationMessage::query()->where('source_type', $notice->getMorphClass())
                ->where('source_id', $notice->id)->selectRaw('channel, status, COUNT(*) as n')->groupBy('channel', 'status')->toBase()->get() : collect(),
            'audienceLabel' => $this->audienceLabel($notice),
        ]);
    }

    public function edit(Notice $notice): View
    {
        $this->authorize('notices.update');
        abort_if($notice->status === 'archived', 404);

        return view('branch.communication.notices.form', $this->formData($notice));
    }

    public function update(Request $request, Notice $notice): RedirectResponse
    {
        $this->authorize('notices.update');
        $this->notices->save($this->validated($request, $notice), $request->file('attachment'), $request->user(), $notice);

        return redirect()->route('branch.communication.notices.show', $notice)->with('success', __('Notice saved.'));
    }

    public function publish(Request $request, Notice $notice): RedirectResponse
    {
        $this->authorize('notices.publish');
        $this->notices->publish($notice, $request->user());

        return back()->with('success', $notice->status === 'scheduled' ? __('Notice scheduled for :time.', ['time' => $notice->publish_at->format('d M Y H:i')]) : __('Notice published.'));
    }

    public function unschedule(Notice $notice): RedirectResponse
    {
        $this->authorize('notices.publish');
        $this->notices->unschedule($notice);

        return back()->with('success', __('The notice is a draft again.'));
    }

    public function archive(Notice $notice): RedirectResponse
    {
        $this->authorize('notices.update');
        $this->notices->archive($notice);

        return back()->with('success', __('Notice archived.'));
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $this->authorize('notices.delete');
        abort_unless($notice->status === 'draft', 403, __('Only drafts can be deleted; archive published notices instead.'));
        if ($notice->attachment_path) {
            Storage::disk('local')->delete($notice->attachment_path);
        }
        $notice->delete();

        return redirect()->route('branch.communication.notices.index')->with('success', __('Draft deleted.'));
    }

    public function attachment(Notice $notice): StreamedResponse
    {
        $this->authorize('notices.view');
        abort_unless($notice->attachment_path && Storage::disk('local')->exists($notice->attachment_path), 404);

        return Storage::disk('local')->download($notice->attachment_path, $notice->attachment_name);
    }

    protected function validated(Request $request, ?Notice $notice = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'body' => ['required', 'string', 'max:10000'],
            'category' => ['required', Rule::in(Notice::CATEGORIES)],
            'audience' => ['required', Rule::in(Notice::AUDIENCES)],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', TenantRule::exists('school_classes', 'id', true)],
            'section_ids' => ['nullable', 'array'],
            'section_ids.*' => ['integer', TenantRule::exists('sections', 'id', true)],
            'student_ids' => ['nullable', 'array', 'max:500'],
            'student_ids.*' => ['integer', TenantRule::exists('students', 'id', true)],
            'is_emergency' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['sms', 'email', 'whatsapp'])],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],
        ]);
        $errors = [];
        if ($data['audience'] === 'classes' && empty($data['class_ids']) && empty($data['section_ids'])) {
            $errors['class_ids'] = __('Choose at least one class or section.');
        }
        if ($data['audience'] === 'students_specific' && empty($data['student_ids'])) {
            $errors['student_ids'] = __('Choose at least one student.');
        }
        if (! empty($data['expires_at']) && ! empty($data['publish_at']) && strtotime($data['expires_at']) <= strtotime($data['publish_at'])) {
            $errors['expires_at'] = __('The notice must expire after it is published.');
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
        if ($data['audience'] !== 'classes') {
            unset($data['class_ids'], $data['section_ids']);
        }
        // Sending SMS / e-mail / WhatsApp costs money: only users allowed to send messages may choose those channels.
        if (! $request->user()->can('notifications.send')) {
            $data['channels'] = $notice?->channels ?? [];
        }
        foreach (['is_emergency', 'is_pinned', 'is_public'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }

    protected function formData(Notice $notice): array
    {
        $filter = (array) $notice->audience_filter;

        return [
            'notice' => $notice,
            'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
            'sections' => Section::query()->with('schoolClass:id,name')->get()->sortBy(fn ($s) => sprintf('%05d%s', $s->schoolClass?->numeric_order ?? 0, $s->name))
                ->mapWithKeys(fn ($s) => [$s->id => $s->schoolClass?->name.' — '.$s->name]),
            'selectedStudents' => Student::query()->whereIn('id', old('student_ids', $filter['student_ids'] ?? []) ?: [0])->get(['id', 'name', 'student_code']),
        ];
    }

    protected function audienceLabel(Notice $notice): string
    {
        $filter = (array) $notice->audience_filter;

        return match ($notice->audience) {
            'classes' => __('Classes').': '.SchoolClass::query()->whereIn('id', $filter['class_ids'] ?? [0])->pluck('name')
                ->merge(Section::query()->with('schoolClass:id,name')->whereIn('id', $filter['section_ids'] ?? [0])->get()->map(fn ($s) => $s->schoolClass?->name.' '.$s->name))->implode(', '),
            'students_specific' => trans_choice(':count student|:count students', count($filter['student_ids'] ?? []), ['count' => count($filter['student_ids'] ?? [])]),
            default => __(\Illuminate\Support\Str::headline($notice->audience)),
        };
    }
}
