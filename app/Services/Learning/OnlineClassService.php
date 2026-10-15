<?php

namespace App\Services\Learning;

use App\Models\Academic\AcademicSession;
use App\Models\Learning\OnlineClass;
use App\Models\Learning\OnlineClassAttendance;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Learning\Meetings\MeetingException;
use App\Services\Learning\Meetings\MeetingProviders;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Online / offline class scheduling. The class row is always saved first; the Zoom / Google Meet call happens
 * afterwards and a provider failure is recorded (sync_status = failed) so it can be retried — a class is never
 * lost because an external service was down. Students and guardians are told about new, moved and cancelled classes.
 */
class OnlineClassService
{
    public function __construct(
        protected LearningAccess $access,
        protected MeetingProviders $providers,
        protected NotificationHub $hub,
        protected RecipientResolver $recipients,
    ) {}

    /** @return array{class:OnlineClass,warning:?string} */
    public function save(array $data, User $user, ?OnlineClass $class = null): array
    {
        $classId = (int) $data['school_class_id'];
        $sectionId = filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null;
        $subjectId = filled($data['subject_id'] ?? null) ? (int) $data['subject_id'] : null;
        $mode = $data['mode'];
        $provider = $mode === 'online' ? ($data['provider'] ?? 'manual') : null;

        if (! $this->access->canTeach($user, $classId, $sectionId)) {
            throw ValidationException::withMessages(['section_id' => __('You can only schedule classes for the sections you teach.')]);
        }
        if (! $this->access->subjectBelongsToClass($classId, $subjectId)) {
            throw ValidationException::withMessages(['subject_id' => __('This subject is not taught in the chosen class.')]);
        }
        if ($class && ! $this->access->manages($user, $class)) {
            abort(403);
        }
        if ($class && $class->status !== 'scheduled') {
            throw ValidationException::withMessages(['title' => __('Only a scheduled class can be changed.')]);
        }
        if ($provider && $provider !== 'manual' && ! $this->providers->isReady($provider)) {
            throw ValidationException::withMessages(['provider' => __(':provider is not connected for this branch yet.', ['provider' => $provider === 'zoom' ? 'Zoom' : 'Google Meet'])]);
        }
        if ($provider === 'manual' && blank($data['join_url'] ?? null)) {
            throw ValidationException::withMessages(['join_url' => __('Paste the meeting link.')]);
        }
        if ($sectionId === null && $this->access->isTeacher($user)) {
            throw ValidationException::withMessages(['section_id' => __('Choose one of your sections.')]);
        }

        $session = AcademicSession::query()->current()->first();
        if (! $session) {
            throw ValidationException::withMessages(['school_class_id' => __('There is no current academic session. Set one under Academics first.')]);
        }

        $wasOnline = $class?->isOnline();
        $oldProvider = $class?->provider;
        $oldExternal = $class?->external_id;
        $oldStart = $class?->starts_at;
        $oldEnd = $class?->ends_at;
        $oldRoom = $class?->room;
        $oldLink = $class?->join_url;

        $class ??= new OnlineClass;
        $class->fill([
            'school_class_id' => $classId, 'section_id' => $sectionId, 'subject_id' => $subjectId,
            'title' => $data['title'], 'description' => $data['description'] ?? null, 'mode' => $mode, 'provider' => $provider,
            'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
            'room' => $mode === 'offline' ? ($data['room'] ?? null) : null,
            'join_url' => $provider === 'manual' ? ($data['join_url'] ?? null) : null,
            'meeting_id' => $provider === 'manual' ? ($data['meeting_id'] ?? null) : null,
            'passcode' => $provider === 'manual' ? ($data['passcode'] ?? null) : null,
        ]);
        $isNew = ! $class->exists;
        if ($isNew) {
            $class->forceFill(['academic_session_id' => $session->id, 'created_by' => $user->id]);
        }
        $class->forceFill(['teacher_id' => $this->access->isTeacher($user) ? $user->id : (filled($data['teacher_id'] ?? null) ? (int) $data['teacher_id'] : ($class->teacher_id ?? $user->id))]);

        // Switching away from (or between) providers drops the old external meeting.
        $warning = null;
        $providerChanged = ! $isNew && ($oldProvider !== $provider);
        if ($providerChanged && $oldExternal && in_array($oldProvider, ['zoom', 'google_meet'], true)) {
            $warning = $this->tryCancelRemote($class, $oldProvider, $oldExternal);
            $class->forceFill(['external_id' => null, 'host_url' => null, 'join_url' => $provider === 'manual' ? $class->join_url : null, 'meeting_id' => $provider === 'manual' ? $class->meeting_id : null, 'passcode' => $provider === 'manual' ? $class->passcode : null]);
        }
        if ($provider === null || $provider === 'manual') {
            $class->forceFill(['external_id' => null, 'host_url' => null, 'sync_status' => 'none', 'sync_error' => null]);
        }

        $class->save();

        if (in_array($provider, ['zoom', 'google_meet'], true)) {
            $warning = $this->sync($class) ?? $warning;
        }

        $moved = ! $isNew && ($oldStart?->ne($class->starts_at) || $oldEnd?->ne($class->ends_at) || $oldRoom !== $class->room || $wasOnline !== $class->isOnline()
            || $oldProvider !== $class->provider || $oldLink !== $class->join_url);
        if ($isNew) {
            $this->announce('online_class.scheduled', $class, $user);
        } elseif ($moved) {
            $this->announce('online_class.rescheduled', $class, $user);
        }

        return ['class' => $class->fresh(), 'warning' => $warning];
    }

    /** Create / update the provider meeting for a class. Returns an error message, or null when synced. */
    public function sync(OnlineClass $class): ?string
    {
        if (! in_array($class->provider, ['zoom', 'google_meet'], true)) {
            return null;
        }
        try {
            $provider = $this->providers->for($class->provider);
            if ($class->external_id) {
                $provider->update($class);
            } else {
                $details = $provider->create($class);
                $class->forceFill([
                    'external_id' => $details->externalId, 'join_url' => $details->joinUrl, 'host_url' => $details->hostUrl,
                    'meeting_id' => $details->meetingId, 'passcode' => $details->passcode,
                ]);
            }
            $class->forceFill(['sync_status' => 'synced', 'sync_error' => null])->save();

            return null;
        } catch (MeetingException $e) {
            $class->forceFill(['sync_status' => 'failed', 'sync_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            return $e->getMessage();
        }
    }

    /** @return ?string error message when the provider call failed (the class is cancelled locally either way) */
    public function cancel(OnlineClass $class, User $user, ?string $reason = null): ?string
    {
        if (! $this->access->manages($user, $class)) {
            abort(403);
        }
        if ($class->status !== 'scheduled') {
            throw ValidationException::withMessages(['reason' => __('Only a scheduled class can be cancelled.')]);
        }

        $warning = $class->external_id ? $this->tryCancelRemote($class, $class->provider, $class->external_id) : null;
        $class->forceFill(['status' => 'cancelled', 'cancel_reason' => $reason ? mb_substr($reason, 0, 255) : null])->save();
        $this->announce('online_class.cancelled', $class, $user);

        return $warning;
    }

    public function complete(OnlineClass $class, User $user): ?string
    {
        if (! $this->access->manages($user, $class)) {
            abort(403);
        }
        if ($class->status !== 'scheduled') {
            throw ValidationException::withMessages(['status' => __('Only a scheduled class can be completed.')]);
        }
        $class->forceFill(['status' => 'completed'])->save();

        return $this->fetchRecording($class);
    }

    /** Ask the provider for the cloud recording (Zoom). Returns an error message, or null. */
    public function fetchRecording(OnlineClass $class): ?string
    {
        if ($class->provider !== 'zoom' || ! $class->external_id) {
            return null;
        }
        try {
            if ($url = $this->providers->for('zoom')->recording($class)) {
                $class->forceFill(['recording_url' => mb_substr($url, 0, 500)])->save();
            }

            return null;
        } catch (MeetingException $e) {
            return $e->getMessage();
        }
    }

    public function setRecording(OnlineClass $class, ?string $url, User $user): void
    {
        if (! $this->access->manages($user, $class)) {
            abort(403);
        }
        $class->forceFill(['recording_url' => $url ? mb_substr($url, 0, 500) : null])->save();
    }

    // ---- attendance -------------------------------------------------------------------------------------------

    /** @return Collection<int,array{student:Student,status:?string}> */
    public function roster(OnlineClass $class): Collection
    {
        $marks = $class->attendances()->pluck('status', 'student_id');

        return $class->audience()->with('currentEnrollment')->orderBy('name')->get()
            ->map(fn (Student $s) => ['student' => $s, 'status' => $marks->get($s->id)])
            ->sortBy(fn ($r) => sprintf('%05d-%s', $r['student']->currentEnrollment?->roll_no ?? 99999, $r['student']->name))->values();
    }

    /** @param  array<int|string,string>  $statuses  student id => present|late|absent|excused|'' (blank clears the mark) */
    public function markAttendance(OnlineClass $class, array $statuses, User $user): int
    {
        if (! $this->access->manages($user, $class)) {
            abort(403);
        }
        $audience = $class->audience()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $saved = 0;

        DB::transaction(function () use ($class, $statuses, $user, $audience, &$saved) {
            foreach ($statuses as $studentId => $status) {
                $studentId = (int) $studentId;
                if (! in_array($studentId, $audience, true)) {
                    continue;   // not a student of this class
                }
                if ($status === '' || $status === null) {
                    OnlineClassAttendance::query()->where(['online_class_id' => $class->id, 'student_id' => $studentId])->delete();

                    continue;
                }
                if (! in_array($status, OnlineClassAttendance::STATUSES, true)) {
                    continue;
                }
                $row = $class->attendances()->firstOrNew(['student_id' => $studentId]);
                $row->forceFill(['status' => $status, 'marked_by' => $user->id, 'marked_at' => now()])->save();
                $saved++;
            }
        });

        return $saved;
    }

    // ---- internals --------------------------------------------------------------------------------------------

    protected function tryCancelRemote(OnlineClass $class, ?string $provider, string $externalId): ?string
    {
        if (! in_array($provider, ['zoom', 'google_meet'], true)) {
            return null;
        }
        try {
            $probe = (clone $class)->forceFill(['external_id' => $externalId, 'provider' => $provider]);
            $this->providers->for($provider)->cancel($probe);

            return null;
        } catch (MeetingException $e) {
            return $e->getMessage();
        }
    }

    protected function announce(string $event, OnlineClass $class, User $user): void
    {
        $class->loadMissing(['subject:id,name,name_bn', 'schoolClass:id,name']);
        $students = $class->audience()->with(['user:id,locale'])->get();
        $fmt = fn ($d) => $d->copy()->setTimezone(config('app.timezone'))->format('d M Y H:i');
        $vars = [
            'title' => $class->title, 'subject' => $class->subject?->displayName() ?? '—', 'class' => $class->schoolClass?->name,
            'when' => $fmt($class->starts_at).' – '.$class->ends_at->copy()->setTimezone(config('app.timezone'))->format('H:i'),
            'place' => $class->isOnline() ? $class->providerLabel().' ('.__('online').')' : ($class->room ?: __('In school')),
            'reason' => $class->cancel_reason ?: '—',
        ];
        $recipients = collect();
        foreach ($students as $student) {
            $recipients->push($this->recipients->student($student, $vars));
        }
        $recipients = $recipients->merge($this->recipients->guardiansOf($students, $vars)->values());

        $this->hub->notify($event, $recipients, $vars, $class, [
            'url' => tenant()->branch()?->url('/student/online-classes'), 'user' => $user,
            'dedupe_suffix' => (string) $class->updated_at?->timestamp,
        ]);
    }
}
