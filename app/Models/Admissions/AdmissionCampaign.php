<?php

namespace App\Models\Admissions;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One admission round for a class in a session: dates, seats, optional admission test, fee,
 * required documents and which optional form fields are shown / required on the online form.
 */
class AdmissionCampaign extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const STATUSES = ['draft', 'open', 'closed'];

    public const DOCUMENT_TYPES = ['birth_certificate', 'photo', 'previous_tc', 'marksheet', 'testimonial', 'other'];

    /** Optional applicant fields an admin can switch on (shown) or make required on the form. */
    public const OPTIONAL_FIELDS = [
        'name_bn', 'birth_registration_no', 'religion', 'blood_group', 'email', 'permanent_address',
        'previous_school', 'previous_class', 'previous_result', 'father_name', 'mother_name', 'guardian_occupation',
        'emergency_contact_phone', 'photo',
    ];

    protected $fillable = [
        'academic_session_id', 'school_class_id', 'title', 'instructions', 'starts_on', 'ends_on', 'seats', 'is_online',
        'has_test', 'test_at', 'test_venue', 'test_total_marks', 'test_pass_marks', 'min_age', 'max_age',
        'admission_fee', 'fee_before_admission', 'required_documents', 'form_settings', 'merit_published', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'test_at' => 'datetime',
            'seats' => 'integer',
            'is_online' => 'boolean',
            'has_test' => 'boolean',
            'fee_before_admission' => 'boolean',
            'merit_published' => 'boolean',
            'test_total_marks' => 'float',
            'test_pass_marks' => 'float',
            'admission_fee' => 'decimal:2',
            'min_age' => 'integer',
            'max_age' => 'integer',
            'required_documents' => 'array',
            'form_settings' => 'array',
            'academic_session_id' => 'integer',
            'school_class_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class);
    }

    /** Status open and today inside the application window. */
    public function isAcceptingApplications(?Carbon $today = null): bool
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return $this->status === 'open'
            && $today->greaterThanOrEqualTo($this->starts_on->copy()->startOfDay())
            && $today->lessThanOrEqualTo($this->ends_on->copy()->startOfDay());
    }

    public function scopeAcceptingOnline(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query->where('status', 'open')->where('is_online', true)
            ->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today);
    }

    /** 'hidden' | 'optional' | 'required' for an optional form field. */
    public function fieldMode(string $field): string
    {
        return $this->form_settings[$field] ?? 'optional';
    }

    public function shows(string $field): bool
    {
        return $this->fieldMode($field) !== 'hidden';
    }

    public function requires(string $field): bool
    {
        return $this->fieldMode($field) === 'required';
    }

    public function requiresFee(): bool
    {
        return (float) $this->admission_fee > 0;
    }

    public function seatsTaken(): int
    {
        return $this->applications()->whereIn('status', ['selected', 'admitted'])->count();
    }
}
