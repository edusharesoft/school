<?php

namespace App\Services\Documents;

use App\Models\Academic\AcademicSession;
use App\Models\Documents\IdCard;
use App\Models\HR\Employee;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Services\Tenancy\SequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ID cards of students and staff: card numbers, validity, the register (active / lost / revoked / replaced) and the data
 * each printed card shows. Printing a card issues it; printing again only counts the reprint.
 */
class IdCardService
{
    public function __construct(protected SequenceService $sequences, protected DocumentSettings $documents) {}

    /** Card for a holder, issued now when the holder has no active card (a lost / expired one is replaced on request, not silently). */
    public function issue(Student|Employee $holder, User $actor, ?CarbonImmutable $validUntil = null): IdCard
    {
        $holder = $this->checkHolder($holder);

        return DB::transaction(function () use ($holder, $actor, $validUntil) {
            $column = $holder instanceof Student ? 'student_id' : 'employee_id';
            $existing = IdCard::query()->where($column, $holder->id)->where('status', 'active')->lockForUpdate()->latest('id')->first();
            if ($existing && ! $existing->isExpired()) {
                return $existing;
            }
            if ($existing) {                               // expired: the new card replaces it
                $existing->forceFill(['status' => 'replaced', 'status_reason' => __('Expired')])->save();
            }

            return $this->create($holder, $actor, $validUntil);
        });
    }

    /** A new card for a lost / damaged one: the old card stops being valid at once. */
    public function reissue(IdCard $card, string $reason, User $actor): IdCard
    {
        if ($card->status === 'replaced') {
            throw ValidationException::withMessages(['card' => __('This card has already been replaced.')]);
        }

        return DB::transaction(function () use ($card, $reason, $actor) {
            $card = IdCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $holder = $card->holder_type === 'student' ? Student::query()->findOrFail($card->student_id) : Employee::query()->findOrFail($card->employee_id);
            $holder = $this->checkHolder($holder);
            $card->forceFill(['status' => $card->status === 'active' ? 'replaced' : $card->status, 'status_reason' => $card->status === 'active' ? $reason : $card->status_reason])->save();
            // Any other still-active card of the holder is replaced too, so only the new one is valid.
            IdCard::query()->where($card->holder_type === 'student' ? 'student_id' : 'employee_id', $holder->id)->where('status', 'active')
                ->update(['status' => 'replaced', 'status_reason' => $reason]);

            return $this->create($holder, $actor, null);
        });
    }

    public function revoke(IdCard $card, string $status, string $reason): IdCard
    {
        if (! in_array($status, ['lost', 'revoked'], true)) {
            throw ValidationException::withMessages(['status' => __('Choose lost or revoked.')]);
        }
        if (! $card->isActive()) {
            throw ValidationException::withMessages(['card' => __('Only an active card can be marked.')]);
        }
        $card->forceFill(['status' => $status, 'status_reason' => mb_substr($reason, 0, 255)])->save();

        return $card;
    }

    /** @param  iterable<IdCard>  $cards */
    public function markPrinted(iterable $cards): void
    {
        foreach ($cards as $card) {
            $card->forceFill(['printed_count' => $card->printed_count + 1, 'last_printed_at' => now()])->save();
        }
    }

    /** End of the month, "card_validity_months" from now (12 by default). */
    public function defaultValidUntil(): CarbonImmutable
    {
        $months = max(1, min(120, (int) $this->documents->get('card_validity_months')));

        return CarbonImmutable::today()->addMonthsNoOverflow($months)->endOfMonth()->startOfDay();
    }

    /**
     * Enrolled students of a session with their active card (if any).
     *
     * @param  array{session?:int,class?:int,section?:int,q?:string}  $filters
     * @return Collection<int,array{student:Student, enrollment:StudentEnrollment, card:?IdCard}>
     */
    public function studentRows(array $filters = []): Collection
    {
        $sessionId = (int) ($filters['session'] ?? 0) ?: AcademicSession::query()->current()->value('id');
        $enrollments = StudentEnrollment::query()
            ->with(['student:id,name,name_bn,student_code,gender,blood_group,photo_path,status', 'schoolClass:id,name,numeric_order', 'section:id,name', 'studyGroup:id,name'])
            ->where('academic_session_id', $sessionId)->where('status', 'enrolled')
            ->when($filters['class'] ?? null, fn ($q, $v) => $q->where('school_class_id', $v))
            ->when($filters['section'] ?? null, fn ($q, $v) => $q->where('section_id', $v))
            ->whereHas('student', fn ($q) => $q->where('status', 'active')->when($filters['q'] ?? null, fn ($w, $t) => $w->search($t)))
            ->get()
            ->sortBy(fn ($e) => sprintf('%05d-%s-%06d', $e->schoolClass?->numeric_order ?? 0, $e->section?->name, $e->roll_no ?? 0))->values();

        $cards = IdCard::query()->where('holder_type', 'student')->whereIn('student_id', $enrollments->pluck('student_id'))->where('status', 'active')->get()->keyBy('student_id');

        return $enrollments->map(fn ($e) => ['student' => $e->student, 'enrollment' => $e, 'card' => $cards->get($e->student_id)]);
    }

    /** @return Collection<int,array{employee:Employee, card:?IdCard}> */
    public function employeeRows(array $filters = []): Collection
    {
        $employees = Employee::query()->with(['designation:id,name,name_bn', 'department:id,name'])->where('status', 'active')
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('employee_type', $v))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->search($t))
            ->orderBy('name')->get();
        $cards = IdCard::query()->where('holder_type', 'employee')->whereIn('employee_id', $employees->pluck('id'))->where('status', 'active')->get()->keyBy('employee_id');

        return $employees->map(fn ($e) => ['employee' => $e, 'card' => $cards->get($e->id)]);
    }

    /**
     * What a printed card shows. Photos are passed as data URIs by the caller's PdfService.
     *
     * @return array<string,mixed>
     */
    public function cardData(IdCard $card, ?StudentEnrollment $enrollment = null): array
    {
        if ($card->holder_type === 'student') {
            $s = $card->student()->with('guardians')->firstOrFail();
            $e = $enrollment ?? StudentEnrollment::query()->with(['schoolClass:id,name', 'section:id,name', 'studyGroup:id,name', 'session:id,name'])
                ->where('student_id', $s->id)->latest('enrolled_on')->latest('id')->first();
            $e?->loadMissing(['schoolClass:id,name', 'section:id,name', 'studyGroup:id,name', 'session:id,name']);
            $guardian = $s->primaryGuardian();

            return [
                'kind' => 'student', 'card' => $card, 'name' => $s->name, 'name_bn' => $s->name_bn, 'code' => $s->student_code,
                'line1' => trim(($e?->schoolClass?->name ?? '').($e?->section ? ' · '.$e->section->name : '').($e?->studyGroup ? ' · '.$e->studyGroup->name : '')),
                'line2' => $e?->roll_no ? __('Roll').': '.$e->roll_no : '',
                'session' => $e?->session?->name, 'blood_group' => $s->blood_group, 'phone' => $guardian?->phone, 'phone_label' => __('Guardian'),
                'photo_path' => $s->photo_path, 'gender' => $s->gender,
            ];
        }

        $m = $card->employee()->with(['designation:id,name,name_bn', 'department:id,name'])->firstOrFail();

        return [
            'kind' => 'employee', 'card' => $card, 'name' => $m->name, 'name_bn' => $m->name_bn, 'code' => $m->employee_code,
            'line1' => (string) ($m->designation ? (app()->getLocale() === 'bn' && $m->designation->name_bn ? $m->designation->name_bn : $m->designation->name) : ''),
            'line2' => (string) ($m->department?->name ?? ''),
            'session' => null, 'blood_group' => $m->blood_group, 'phone' => $m->phone, 'phone_label' => __('Phone'),
            'photo_path' => $m->photo_path, 'gender' => $m->gender,
        ];
    }

    protected function create(Student|Employee $holder, User $actor, ?CarbonImmutable $validUntil): IdCard
    {
        $student = $holder instanceof Student;
        $prefix = $student ? 'IDS' : 'IDE';

        return IdCard::query()->create([
            'holder_type' => $student ? 'student' : 'employee',
            'student_id' => $student ? $holder->id : null, 'employee_id' => $student ? null : $holder->id,
            'card_no' => sprintf('%s-%06d', $prefix, $this->sequences->next('id_card_'.($student ? 'student' : 'employee'))),
            'verification_code' => $this->uniqueCode(),
            'issued_on' => now()->toDateString(), 'valid_until' => ($validUntil ?? $this->defaultValidUntil())->toDateString(),
            'status' => 'active', 'issued_by' => $actor->id,
        ]);
    }

    protected function checkHolder(Student|Employee $holder): Student|Employee
    {
        if ($holder instanceof Student && ! $holder->isActive()) {
            throw ValidationException::withMessages(['card' => __('Only an active student gets an ID card.')]);
        }
        if ($holder instanceof Employee && ! $holder->isActive()) {
            throw ValidationException::withMessages(['card' => __('Only an active staff member gets an ID card.')]);
        }

        return $holder;
    }

    protected function uniqueCode(): string
    {
        do {
            $code = substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 10);
        } while (IdCard::withoutGlobalScopes()->where('verification_code', $code)->exists());

        return $code;
    }
}
