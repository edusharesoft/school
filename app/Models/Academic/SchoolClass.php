<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A class / grade (Play, One … Ten, XI, XII, Honours 1st year …). "Class" is a reserved word, hence SchoolClass. */
class SchoolClass extends Model
{
    use Auditable, BelongsToBranch, HasFactory, SoftDeletes;

    public const LEVELS = ['pre_primary', 'primary', 'junior_secondary', 'secondary', 'higher_secondary', 'tertiary'];

    protected $fillable = ['name', 'name_bn', 'code', 'level', 'numeric_order', 'has_groups', 'is_active'];

    protected function casts(): array
    {
        return [
            'numeric_order' => 'integer',
            'has_groups' => 'boolean',
            'is_active' => 'boolean',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public static function levelLabel(string $level): string
    {
        return $level === 'primary' ? __('Primary level') : __(ucwords(str_replace('_', ' ', $level)));
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('name');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('numeric_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Next class in promotion order within the same branch. */
    public function nextClass(): ?self
    {
        return static::query()->active()->where('numeric_order', '>', $this->numeric_order)->ordered()->first();
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
