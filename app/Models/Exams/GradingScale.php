<?php

namespace App\Models\Exams;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Grade bands on percentage (highest first) + GPA rules. Nothing is hard-coded: the Bangladesh
 * secondary / higher-secondary scale is only the default offered when a branch has none.
 */
class GradingScale extends Model
{
    use Auditable, BelongsToBranch, SoftDeletes;

    /** Default scale used by SSC / HSC boards (percentage based). */
    public const BANGLADESH_DEFAULT = [
        ['grade' => 'A+', 'min' => 80, 'max' => 100, 'point' => 5.00, 'remark' => 'Outstanding'],
        ['grade' => 'A', 'min' => 70, 'max' => 79.99, 'point' => 4.00, 'remark' => 'Excellent'],
        ['grade' => 'A-', 'min' => 60, 'max' => 69.99, 'point' => 3.50, 'remark' => 'Very good'],
        ['grade' => 'B', 'min' => 50, 'max' => 59.99, 'point' => 3.00, 'remark' => 'Good'],
        ['grade' => 'C', 'min' => 40, 'max' => 49.99, 'point' => 2.00, 'remark' => 'Satisfactory'],
        ['grade' => 'D', 'min' => 33, 'max' => 39.99, 'point' => 1.00, 'remark' => 'Pass'],
        ['grade' => 'F', 'min' => 0, 'max' => 32.99, 'point' => 0.00, 'remark' => 'Fail'],
    ];

    protected $fillable = ['name', 'grades', 'max_gpa', 'optional_base_point', 'gpa_decimals', 'rounding', 'is_default'];

    protected function casts(): array
    {
        return [
            'grades' => 'array',
            'max_gpa' => 'float',
            'optional_base_point' => 'float',
            'gpa_decimals' => 'integer',
            'is_default' => 'boolean',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    /** The branch's default scale; the Bangladesh board scale is created the first time one is needed. */
    public static function defaultForBranch(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('id')->first()
            ?? static::query()->create([
                'name' => 'Bangladesh SSC/HSC (GPA 5.00)',
                'grades' => self::BANGLADESH_DEFAULT,
                'max_gpa' => 5.00,
                'optional_base_point' => 2.00,
                'gpa_decimals' => 2,
                'rounding' => 'round',
                'is_default' => true,
            ]);
    }

    /** Array shape used by GradingService::gpa(). */
    public function rules(): array
    {
        return [
            'grades' => $this->grades,
            'max_gpa' => $this->max_gpa,
            'optional_base_point' => $this->optional_base_point,
            'gpa_decimals' => $this->gpa_decimals,
            'rounding' => $this->rounding,
        ];
    }

    /** Failing band = the band with grade point 0 (or the lowest band). */
    public function failGrade(): string
    {
        $bands = collect($this->grades)->sortBy('point');

        return (string) ($bands->firstWhere('point', 0)['grade'] ?? $bands->first()['grade'] ?? 'F');
    }
}
