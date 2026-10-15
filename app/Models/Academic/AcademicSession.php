<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Branch-specific academic session. Every enrollment, attendance day, exam and invoice hangs off one. */
class AcademicSession extends Model
{
    use Auditable, BelongsToBranch, HasFactory;

    protected $fillable = ['academic_year_id', 'name', 'starts_on', 'ends_on', 'is_current', 'status'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'institution_id' => 'integer',
            'branch_id' => 'integer',
            'academic_year_id' => 'integer',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** The current session of the branch in context (null if none is marked current). */
    public static function currentForBranch(): ?self
    {
        return static::query()->current()->latest('starts_on')->first();
    }
}
