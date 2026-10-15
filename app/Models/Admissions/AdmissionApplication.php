<?php

namespace App\Models\Admissions;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdmissionApplication extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    public const STATUSES = ['submitted', 'verified', 'selected', 'waiting', 'rejected', 'admitted', 'cancelled'];

    /** Statuses that still take part in the merit list. */
    public const IN_RACE = ['submitted', 'verified', 'selected', 'waiting'];

    protected $fillable = [
        'admission_campaign_id', 'source', 'name', 'name_bn', 'gender', 'date_of_birth', 'birth_registration_no', 'religion',
        'blood_group', 'phone', 'email', 'present_address', 'permanent_address', 'previous_school', 'previous_class',
        'previous_result', 'father_name', 'mother_name', 'guardian_name', 'guardian_phone', 'guardian_relation',
        'guardian_occupation', 'emergency_contact_phone', 'remarks',
    ];

    protected $guarded = ['application_no', 'status', 'student_id', 'test_marks', 'merit_position', 'waiting_position', 'fee_status'];

    protected array $auditExclude = ['submitted_ip'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'test_marks' => 'float',
            'test_absent' => 'boolean',
            'merit_position' => 'integer',
            'waiting_position' => 'integer',
            'fee_amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'fee_recorded_at' => 'datetime',
            'admission_campaign_id' => 'integer',
            'student_id' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdmissionCampaign::class, 'admission_campaign_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('name_bn', 'like', "%{$term}%")
                ->orWhere('application_no', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('guardian_phone', 'like', "%{$term}%")
                ->orWhere('birth_registration_no', $term);
        });
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['admitted', 'rejected', 'cancelled'], true);
    }

    public function feeSettled(): bool
    {
        return (float) $this->fee_amount <= 0 || in_array($this->fee_status, ['paid', 'waived'], true);
    }

    public function tone(): string
    {
        return match ($this->status) {
            'selected', 'admitted' => 'success',
            'waiting' => 'warning',
            'rejected', 'cancelled' => 'danger',
            'verified' => 'info',
            default => 'secondary',
        };
    }

    /** Public status text (no internal remarks). */
    public function publicStatus(): string
    {
        return __(match ($this->status) {
            'submitted' => 'Application received',
            'verified' => 'Verified — waiting for the result',
            'selected' => 'Selected for admission',
            'waiting' => 'On the waiting list',
            'rejected' => 'Not selected',
            'admitted' => 'Admitted',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        });
    }
}
