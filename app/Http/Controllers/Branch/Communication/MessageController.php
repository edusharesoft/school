<?php

namespace App\Http\Controllers\Branch\Communication;

use App\Http\Controllers\Controller;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Communication\Notice;
use App\Models\Communication\NotificationBatch;
use App\Models\Communication\NotificationMessage;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\NotificationSettings;
use App\Services\Notifications\RecipientResolver;
use App\Support\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Bulk / scheduled messages, message history with delivery status, retries and the usage report. */
class MessageController extends Controller
{
    public function __construct(protected NotificationHub $hub, protected NotificationSettings $settings) {}

    public function index(Request $request): View
    {
        $this->authorize('notifications.view');
        $from = $request->date('from') ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?? now();

        $query = NotificationMessage::query()->where('channel', '!=', 'in_app')
            ->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())
            ->when($request->input('channel'), fn ($q, $c) => $q->where('channel', $c))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('event'), fn ($q, $e) => $q->where('event', $e))
            ->when($request->integer('batch'), fn ($q, $b) => $q->where('notification_batch_id', $b))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('to', 'like', "%$t%")->orWhere('recipient_name', 'like', "%$t%")));

        return view('branch.communication.messages.index', [
            'messages' => (clone $query)->latest('id')->paginate(40)->withQueryString(),
            'counts' => (clone $query)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'batches' => NotificationBatch::query()->with('creator:id,name')->latest('id')->limit(8)->get(),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function create(): View
    {
        $this->authorize('notifications.send');

        return view('branch.communication.messages.create', [
            'channels' => $this->settings->enabledChannels(),
            'classes' => SchoolClass::query()->orderBy('numeric_order')->pluck('name', 'id'),
            'sections' => Section::query()->with('schoolClass:id,name,numeric_order')->get()->sortBy(fn ($s) => sprintf('%05d%s', $s->schoolClass?->numeric_order ?? 0, $s->name))
                ->mapWithKeys(fn ($s) => [$s->id => $s->schoolClass?->name.' — '.$s->name]),
        ]);
    }

    public function store(Request $request, RecipientResolver $resolver): RedirectResponse
    {
        $this->authorize('notifications.send');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'audience' => ['required', Rule::in(Notice::AUDIENCES)],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer', TenantRule::exists('school_classes', 'id', true)],
            'section_ids' => ['nullable', 'array'],
            'section_ids.*' => ['integer', TenantRule::exists('sections', 'id', true)],
            'student_ids' => ['nullable', 'array', 'max:500'],
            'student_ids.*' => ['integer', TenantRule::exists('students', 'id', true)],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in($this->settings->enabledChannels())],
            'body_bn' => ['nullable', 'string', 'max:1000', 'required_without:body_en'],
            'body_en' => ['nullable', 'string', 'max:1000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'is_emergency' => ['nullable', 'boolean'],
        ]);
        if ($data['audience'] === 'classes' && empty($data['class_ids']) && empty($data['section_ids'])) {
            throw ValidationException::withMessages(['class_ids' => __('Choose at least one class or section.')]);
        }
        if ($data['audience'] === 'students_specific' && empty($data['student_ids'])) {
            throw ValidationException::withMessages(['student_ids' => __('Choose at least one student.')]);
        }
        $filter = array_filter(['class_ids' => $data['class_ids'] ?? [], 'section_ids' => $data['section_ids'] ?? [], 'student_ids' => $data['student_ids'] ?? []]);
        $recipients = $resolver->audience($data['audience'], $filter);
        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['audience' => __('Nobody matches this audience.')]);
        }
        $scheduledAt = ! empty($data['scheduled_at']) ? CarbonImmutable::parse($data['scheduled_at']) : null;

        [$batch, $stats] = DB::transaction(function () use ($data, $filter, $recipients, $scheduledAt, $request) {
            $batch = new NotificationBatch([
                'title' => $data['title'], 'channels' => $data['channels'], 'audience' => ['type' => $data['audience']] + $filter,
                'body_bn' => $data['body_bn'] ?? null, 'body_en' => $data['body_en'] ?? null, 'recipients' => $recipients->count(),
                'scheduled_at' => $scheduledAt, 'is_emergency' => $request->boolean('is_emergency'),
            ]);
            $batch->forceFill(['created_by' => $request->user()->id])->save();
            $stats = $this->hub->notify('message.general', $recipients, [], null, [
                'batch' => $batch, 'channels' => $data['channels'], 'bodies' => array_filter(['bn' => $data['body_bn'] ?? null, 'en' => $data['body_en'] ?? null]),
                'scheduled_at' => $scheduledAt, 'force' => $batch->is_emergency, 'user' => $request->user(),
            ]);

            return [$batch, $stats];
        });

        return redirect()->route('branch.communication.messages.index', ['batch' => $batch->id, 'from' => now()->subDay()->toDateString()])
            ->with('success', __(':created message(s) for :people recipient(s): :queued queued, :skipped skipped (opt-out / no template), :missing without a contact.', [
                'created' => $stats['created'], 'people' => $recipients->count(), 'queued' => $stats['queued'], 'skipped' => $stats['skipped'], 'missing' => $stats['no_address'],
            ]));
    }

    public function show(NotificationMessage $message): View
    {
        $this->authorize('notifications.view');

        return view('branch.communication.messages.show', ['message' => $message->load('batch', 'user:id,name')]);
    }

    public function retry(NotificationMessage $message): RedirectResponse
    {
        $this->authorize('notifications.send');

        return $this->hub->retry($message)
            ? back()->with('success', __('The message is queued again.'))
            : back()->withErrors(['status' => __('Only failed or cancelled messages can be retried.')]);
    }

    public function cancel(NotificationMessage $message): RedirectResponse
    {
        $this->authorize('notifications.send');

        return $this->hub->cancel($message)
            ? back()->with('success', __('Message cancelled.'))
            : back()->withErrors(['status' => __('Only scheduled or queued messages can be cancelled.')]);
    }

    public function cancelBatch(NotificationBatch $batch): RedirectResponse
    {
        $this->authorize('notifications.send');
        $count = NotificationMessage::query()->where('notification_batch_id', $batch->id)->where('status', 'scheduled')->update(['status' => 'cancelled', 'updated_at' => now()]);

        return back()->with('success', __(':count scheduled message(s) cancelled.', ['count' => $count]));
    }

    public function retryFailed(Request $request): RedirectResponse
    {
        $this->authorize('notifications.send');
        $count = 0;
        NotificationMessage::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))
            ->when($request->integer('batch'), fn ($q, $b) => $q->where('notification_batch_id', $b))
            ->limit(1000)->get()->each(function ($m) use (&$count) {
                $count += (int) $this->hub->retry($m);
            });

        return back()->with('success', __(':count failed message(s) queued again.', ['count' => $count]));
    }

    /** Student picker for "specific students" audiences (JSON). */
    public function students(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()->can('notices.create') || $request->user()->can('notifications.send'), 403);
        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }

        return response()->json(\App\Models\Students\Student::query()->active()->with('currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name')
            ->where(fn ($q) => $q->where('name', 'like', "%$term%")->orWhere('student_code', 'like', "%$term%"))
            ->orderBy('name')->limit(20)->get()
            ->map(fn ($s) => ['id' => $s->id, 'label' => $s->name.' ('.$s->student_code.') '.trim(($s->currentEnrollment?->schoolClass?->name ?? '').' '.($s->currentEnrollment?->section?->name ?? ''))]));
    }

    /** Messages per month × channel × status, SMS parts, and messages per event. */
    public function usage(Request $request): View
    {
        $this->authorize('notifications.view');
        $from = ($request->date('from') ?? now()->subMonths(5))->startOfMonth();
        $to = $request->date('to') ?? now();
        $rows = NotificationMessage::query()->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())
            ->get(['created_at', 'channel', 'status', 'segments', 'event']);

        $months = $rows->groupBy(fn ($m) => $m->created_at->format('Y-m'))->sortKeys()->map(fn ($g) => [
            'by_channel' => $g->groupBy('channel')->map(fn ($c) => [
                'total' => $c->count(),
                'sent' => $c->whereIn('status', ['sent', 'delivered'])->count(),
                'failed' => $c->where('status', 'failed')->count(),
                'skipped' => $c->where('status', 'skipped')->count(),
            ]),
            'sms_parts' => (int) $g->where('channel', 'sms')->whereIn('status', ['sent', 'delivered'])->sum('segments'),
        ]);

        return view('branch.communication.messages.usage', [
            'months' => $months, 'from' => $from, 'to' => $to,
            'byEvent' => $rows->groupBy('event')->map(fn ($g) => $g->groupBy('channel')->map->count())->sortKeys(),
            'totals' => [
                'sms_parts' => (int) $rows->where('channel', 'sms')->whereIn('status', ['sent', 'delivered'])->sum('segments'),
                'sms' => $rows->where('channel', 'sms')->whereIn('status', ['sent', 'delivered'])->count(),
                'email' => $rows->where('channel', 'email')->whereIn('status', ['sent', 'delivered'])->count(),
                'whatsapp' => $rows->where('channel', 'whatsapp')->whereIn('status', ['sent', 'delivered'])->count(),
                'failed' => $rows->where('status', 'failed')->count(),
            ],
        ]);
    }
}
