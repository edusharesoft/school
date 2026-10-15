<?php

namespace App\Services\Admissions;

use App\Events\Admissions\StudentAdmitted;
use App\Events\Admissions\AdmissionApplicationSubmitted;
use App\Models\Academic\Section;
use App\Models\Admissions\AdmissionApplication;
use App\Models\Admissions\AdmissionCampaign;
use App\Models\Students\Student;
use App\Models\Students\StudentDocument;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Students\StudentService;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Admission workflow (master prompt §8):
 *   submit (online / offline) → verify → test marks → merit & waiting list → fee → admit (student + enrollment)
 * Every state change is audit-logged; the student is created through StudentService::admit so the
 * Part 3A rules (IDs, one enrollment per session, capacity, roll numbers) apply unchanged.
 */
class AdmissionService
{
    public function __construct(
        protected TenantContext $tenant,
        protected SequenceService $sequences,
        protected StudentService $students,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array<string,UploadedFile>  $documents  type => file
     */
    public function submit(AdmissionCampaign $campaign, array $data, array $documents, ?UploadedFile $photo, string $source, ?User $actor = null, ?string $ip = null): AdmissionApplication
    {
        if ($source === 'online' && (! $campaign->is_online || ! $campaign->isAcceptingApplications())) {
            throw ValidationException::withMessages(['campaign' => __('This admission is not accepting online applications.')]);
        }
        if ($source === 'offline' && $campaign->status !== 'open') {
            throw ValidationException::withMessages(['campaign' => __('Open the admission before entering applications.')]);
        }

        $this->assertAge($campaign, CarbonImmutable::parse($data['date_of_birth']));

        return DB::transaction(function () use ($campaign, $data, $documents, $photo, $source, $actor, $ip) {
            // Serialise submissions for one campaign so the duplicate check is race-free.
            AdmissionCampaign::query()->whereKey($campaign->id)->lockForUpdate()->first();
            $this->assertNotDuplicate($campaign, $data);

            $application = new AdmissionApplication(Arr::only($data, (new AdmissionApplication)->getFillable()));
            $application->admission_campaign_id = $campaign->id;
            $application->source = $source;
            $application->application_no = $this->nextNumber();
            $application->status = 'submitted';
            $application->fee_amount = $campaign->admission_fee;
            $application->fee_status = 'unpaid';
            $application->created_by = $actor?->id;
            $application->submitted_ip = $ip;
            $application->save();

            $folder = $this->tenant->branch()->storagePath('admissions/'.$application->id);
            if ($photo) {
                $application->forceFill(['photo_path' => $photo->store($folder, 'local')])->save();
            }
            foreach ($documents as $type => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $application->documents()->create([
                    'type' => $type,
                    'title' => __(ucfirst(str_replace('_', ' ', $type))),
                    'disk' => 'local',
                    'path' => $file->store($folder, 'local'),
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 191),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }
            AdmissionApplicationSubmitted::dispatch($application);

            return $application;
        });
    }

    /** Branch code + A + 2-digit year + 5-digit running number, e.g. BR01-A26-00042. */
    public function nextNumber(): string
    {
        $year = now()->format('Y');

        return sprintf('%s-A%s-%05d', strtoupper($this->tenant->branch()->code), substr($year, -2), $this->sequences->next('admission_no', $year));
    }

    /** verified | rejected | cancelled (manual decisions). */
    public function setStatus(AdmissionApplication $application, string $status, ?string $remarks, User $actor): AdmissionApplication
    {
        abort_unless(in_array($status, ['verified', 'rejected', 'cancelled'], true), 422);

        if ($application->isFinal()) {
            throw ValidationException::withMessages(['status' => __('This application is already closed.')]);
        }

        $application->forceFill([
            'status' => $status,
            'remarks' => $remarks ?? $application->remarks,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'merit_position' => $status === 'verified' ? $application->merit_position : null,
            'waiting_position' => $status === 'verified' ? $application->waiting_position : null,
        ])->save();

        return $application;
    }

    /**
     * @param  array<int,array{marks?:mixed,absent?:mixed}>  $rows  application id => marks / absent
     */
    public function recordMarks(AdmissionCampaign $campaign, array $rows, User $actor): int
    {
        if (! $campaign->has_test) {
            throw ValidationException::withMessages(['marks' => __('This admission has no admission test.')]);
        }

        return DB::transaction(function () use ($campaign, $rows, $actor) {
            $applications = $campaign->applications()->whereIn('id', array_keys($rows))->whereIn('status', AdmissionApplication::IN_RACE)->get()->keyBy('id');
            $saved = 0;

            foreach ($rows as $id => $row) {
                $application = $applications->get((int) $id);
                if (! $application) {
                    continue;
                }
                $absent = ! empty($row['absent']);
                $marks = $absent || ! isset($row['marks']) || $row['marks'] === '' ? null : round((float) $row['marks'], 2);

                if ($marks !== null && ($marks < 0 || $marks > (float) $campaign->test_total_marks)) {
                    throw ValidationException::withMessages(["rows.{$id}.marks" => __('Marks must be between 0 and :max.', ['max' => $campaign->test_total_marks])]);
                }

                $application->forceFill(['test_marks' => $marks, 'test_absent' => $absent])->save();
                $saved++;
            }

            $this->audit->log('admissions.marks_recorded', $campaign, [], ['count' => $saved], null, $actor->id);

            return $saved;
        });
    }

    /**
     * (Re)build the merit list. Test: absentees and marks below the pass mark are not selected; others are
     * ranked by marks (ties: earlier application first). No test: first come, first served.
     * The best N (free seats) are "selected", the rest "waiting" with a waiting position.
     *
     * @return array{selected:int, waiting:int, rejected:int}
     */
    public function generateMerit(AdmissionCampaign $campaign, User $actor): array
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $campaign = AdmissionCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $candidates = $campaign->applications()->whereIn('status', AdmissionApplication::IN_RACE)->orderBy('created_at')->orderBy('id')->get();

            $rejected = collect();
            if ($campaign->has_test) {
                $missing = $candidates->filter(fn ($a) => ! $a->test_absent && $a->test_marks === null)->count();
                if ($missing > 0) {
                    throw ValidationException::withMessages(['merit' => __('Enter test marks (or mark absent) for all :count remaining applicant(s) first.', ['count' => $missing])]);
                }

                [$candidates, $rejected] = $candidates->partition(fn ($a) => ! $a->test_absent
                    && ($campaign->test_pass_marks === null || $a->test_marks >= $campaign->test_pass_marks));

                $candidates = $candidates->sort(fn ($a, $b) => [$b->test_marks, $a->created_at, $a->id] <=> [$a->test_marks, $b->created_at, $b->id])->values();
            }

            $free = max(0, $campaign->seats - $campaign->applications()->where('status', 'admitted')->count());
            $stats = ['selected' => 0, 'waiting' => 0, 'rejected' => 0];

            foreach ($candidates->values() as $i => $application) {
                $selected = $i < $free;
                $application->forceFill([
                    'status' => $selected ? 'selected' : 'waiting',
                    'merit_position' => $i + 1,
                    'waiting_position' => $selected ? null : $i + 1 - $free,
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                ])->save();
                $stats[$selected ? 'selected' : 'waiting']++;
            }

            foreach ($rejected as $application) {
                $application->forceFill([
                    'status' => 'rejected',
                    'merit_position' => null,
                    'waiting_position' => null,
                    'remarks' => $application->test_absent ? __('Absent in the admission test') : __('Below the pass mark'),
                    'decided_by' => $actor->id,
                    'decided_at' => now(),
                ])->save();
                $stats['rejected']++;
            }

            $this->audit->log('admissions.merit_generated', $campaign, [], $stats, null, $actor->id);

            return $stats;
        });
    }

    /** Offer the next seat to the first applicant on the waiting list (after a selected applicant drops out). */
    public function promoteFromWaiting(AdmissionCampaign $campaign, User $actor): ?AdmissionApplication
    {
        return DB::transaction(function () use ($campaign, $actor) {
            $occupied = $campaign->applications()->whereIn('status', ['selected', 'admitted'])->count();
            if ($occupied >= $campaign->seats) {
                throw ValidationException::withMessages(['merit' => __('All seats are taken.')]);
            }

            $next = $campaign->applications()->where('status', 'waiting')->orderBy('waiting_position')->lockForUpdate()->first();
            if (! $next) {
                return null;
            }

            $next->forceFill(['status' => 'selected', 'waiting_position' => null, 'decided_by' => $actor->id, 'decided_at' => now()])->save();

            // Everyone behind moves up one place.
            $campaign->applications()->where('status', 'waiting')->orderBy('waiting_position')->get()
                ->each(fn ($a, $i) => $a->forceFill(['waiting_position' => $i + 1])->save());

            return $next;
        });
    }

    /** paid | waived | unpaid — recorded here until Part 4 finance issues the admission-fee invoice. */
    public function recordFee(AdmissionApplication $application, string $status, ?string $reference, User $actor): AdmissionApplication
    {
        abort_unless(in_array($status, ['paid', 'waived', 'unpaid'], true), 422);

        $application->forceFill([
            'fee_status' => $status,
            'fee_reference' => $reference,
            'fee_recorded_at' => $status === 'unpaid' ? null : now(),
        ])->save();

        $this->audit->log('admissions.fee_'.$status, $application, [], ['amount' => (string) $application->fee_amount, 'reference' => $reference], null, $actor->id);

        return $application;
    }

    /** Create the student, guardians and first enrollment from a selected application. */
    public function admit(AdmissionApplication $application, int $sectionId, ?int $studyGroupId, ?int $rollNo, User $actor): Student
    {
        $application->loadMissing(['campaign', 'documents']);
        $campaign = $application->campaign;

        if ($application->status !== 'selected') {
            throw ValidationException::withMessages(['status' => __('Only selected applicants can be admitted.')]);
        }
        if ($campaign->fee_before_admission && ! $application->feeSettled()) {
            throw ValidationException::withMessages(['fee' => __('The admission fee must be paid or waived before admission.')]);
        }

        $section = Section::query()->findOrFail($sectionId);
        if ($section->school_class_id !== $campaign->school_class_id) {
            throw ValidationException::withMessages(['section_id' => __('The section does not belong to the admission class.')]);
        }

        return DB::transaction(function () use ($application, $campaign, $section, $studyGroupId, $rollNo, $actor) {
            $locked = AdmissionApplication::query()->lockForUpdate()->findOrFail($application->id);
            if ($locked->status !== 'selected') {
                throw ValidationException::withMessages(['status' => __('Only selected applicants can be admitted.')]);
            }

            $guardians = [[
                'name' => $application->guardian_name,
                'phone' => $application->guardian_phone,
                'relation' => $application->guardian_relation,
                'occupation' => $application->guardian_occupation,
                'is_primary' => true,
            ]];

            $student = $this->students->admit([
                'name' => $application->name,
                'name_bn' => $application->name_bn,
                'gender' => $application->gender,
                'date_of_birth' => $application->date_of_birth->toDateString(),
                'birth_registration_no' => $application->birth_registration_no,
                'blood_group' => $application->blood_group,
                'religion' => $application->religion,
                'nationality' => 'Bangladeshi',
                'phone' => $application->phone,
                'email' => $application->email,
                'present_address' => $application->present_address,
                'permanent_address' => $application->permanent_address,
                'previous_school' => $application->previous_school,
                'admission_date' => now()->toDateString(),
                'emergency_contact_name' => $application->guardian_name,
                'emergency_contact_phone' => $application->emergency_contact_phone ?: $application->guardian_phone,
            ], $guardians, [
                'academic_session_id' => $campaign->academic_session_id,
                'school_class_id' => $campaign->school_class_id,
                'section_id' => $section->id,
                'study_group_id' => $studyGroupId,
                'roll_no' => $rollNo,
            ]);

            $this->copyFiles($application, $student, $actor);

            $locked->forceFill([
                'status' => 'admitted',
                'student_id' => $student->id,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ])->save();
            StudentAdmitted::dispatch($student);

            return $student;
        });
    }

    protected function copyFiles(AdmissionApplication $application, Student $student, User $actor): void
    {
        $disk = Storage::disk('local');
        $folder = $this->tenant->branch()->storagePath('students/'.$student->id);

        if ($application->photo_path && $disk->exists($application->photo_path)) {
            $target = $folder.'/'.basename($application->photo_path);
            $disk->copy($application->photo_path, $target);
            $student->forceFill(['photo_path' => $target])->save();
        }

        foreach ($application->documents as $document) {
            if (! $disk->exists($document->path)) {
                continue;
            }
            $target = $folder.'/documents/'.basename($document->path);
            $disk->copy($document->path, $target);
            $student->documents()->create([
                'type' => in_array($document->type, StudentDocument::TYPES, true) ? $document->type : 'other',
                'title' => $document->title,
                'disk' => 'local',
                'path' => $target,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'uploaded_by' => $actor->id,
            ]);
        }
    }

    protected function assertAge(AdmissionCampaign $campaign, CarbonImmutable $dob): void
    {
        if ($campaign->min_age === null && $campaign->max_age === null) {
            return;
        }

        $campaign->loadMissing('session');
        $reference = CarbonImmutable::parse($campaign->session?->starts_on ?? now());
        $age = (int) floor($dob->diffInYears($reference));

        if (($campaign->min_age !== null && $age < $campaign->min_age) || ($campaign->max_age !== null && $age > $campaign->max_age)) {
            throw ValidationException::withMessages(['date_of_birth' => __('Age on :date must be between :min and :max years (applicant: :age).', [
                'date' => $reference->format('d M Y'), 'min' => $campaign->min_age ?? 0, 'max' => $campaign->max_age ?? '—', 'age' => $age,
            ])]);
        }
    }

    protected function assertNotDuplicate(AdmissionCampaign $campaign, array $data): void
    {
        $query = $campaign->applications()->whereNotIn('status', ['cancelled']);

        $duplicate = ! empty($data['birth_registration_no'])
            ? (clone $query)->where('birth_registration_no', $data['birth_registration_no'])->exists()
            : false;

        $duplicate = $duplicate || (clone $query)->where('name', $data['name'])
            ->whereDate('date_of_birth', CarbonImmutable::parse($data['date_of_birth'])->toDateString())
            ->where('guardian_phone', $data['guardian_phone'])->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => __('An application for this applicant already exists in this admission. Use the status check to find it.')]);
        }
    }
}
