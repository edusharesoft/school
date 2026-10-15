<?php

namespace App\Models\Learning;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\Subject;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A scheduled live class: online (Zoom / Google Meet / pasted link) or offline (a room). */
class OnlineClass extends Model
{
    use Auditable, BelongsToBranch;

    public const MODES = ['online', 'offline'];

    public const PROVIDERS = ['zoom', 'google_meet', 'manual'];

    /** Hosts' links and the passcode are not shown in audit logs. */
    protected array $auditExclude = ['host_url', 'passcode'];

    protected $fillable = ['school_class_id', 'section_id', 'subject_id', 'title', 'description', 'mode', 'provider', 'starts_at', 'ends_at', 'room', 'join_url', 'meeting_id', 'passcode'];

    protected $hidden = ['host_url'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'school_class_id' => 'integer', 'section_id' => 'integer', 'subject_id' => 'integer', 'teacher_id' => 'integer',
            'academic_session_id' => 'integer', 'created_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
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

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(OnlineClassAttendance::class);
    }

    public function scopeForEnrollment(Builder $query, StudentEnrollment $enrollment): Builder
    {
        return $query->where('academic_session_id', $enrollment->academic_session_id)
            ->where('school_class_id', $enrollment->school_class_id)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $enrollment->section_id));
    }

    public function isOnline(): bool
    {
        return $this->mode === 'online';
    }

    /** scheduled | live | ended | completed | cancelled — "live" and "ended" are derived from the clock. */
    public function phase(): string
    {
        if ($this->status !== 'scheduled') {
            return $this->status;
        }
        if ($this->starts_at->isFuture()) {
            return 'scheduled';
        }

        return $this->ends_at->isFuture() ? 'live' : 'ended';
    }

    /** Students may use the link from 15 minutes before the start until the end. */
    public function joinable(): bool
    {
        return $this->isOnline() && $this->status === 'scheduled' && $this->join_url
            && now()->gte($this->starts_at->copy()->subMinutes(15)) && $this->ends_at->isFuture();
    }

    public function tone(): string
    {
        return match ($this->phase()) {
            'live' => 'success', 'scheduled' => 'primary', 'cancelled' => 'danger', 'completed' => 'secondary', default => 'warning',
        };
    }

    public function providerLabel(): string
    {
        return match ($this->provider) {
            'zoom' => 'Zoom', 'google_meet' => 'Google Meet', 'manual' => __('Link'), default => '—',
        };
    }

    /** Students this class is meant for (enrolled in the session, class and section). */
    public function audience(): Builder
    {
        $enrolled = StudentEnrollment::query()->where('academic_session_id', $this->academic_session_id)
            ->where('school_class_id', $this->school_class_id)->where('status', 'enrolled')
            ->when($this->section_id, fn ($q, $s) => $q->where('section_id', $s))->select('student_id');

        return Student::query()->whereIn('id', $enrolled);
    }
}
