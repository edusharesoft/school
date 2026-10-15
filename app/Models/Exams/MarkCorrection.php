<?php

namespace App\Models\Exams;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Request to change marks once marks entry has closed (results processing onwards). */
class MarkCorrection extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['exam_mark_id', 'old_marks', 'old_absent', 'new_marks', 'new_absent', 'reason'];

    protected $guarded = ['status', 'requested_by', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return [
            'old_marks' => 'array',
            'new_marks' => 'array',
            'old_absent' => 'boolean',
            'new_absent' => 'boolean',
            'reviewed_at' => 'datetime',
            'exam_mark_id' => 'integer',
            'requested_by' => 'integer',
            'reviewed_by' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
        ];
    }

    public function mark(): BelongsTo
    {
        return $this->belongsTo(ExamMark::class, 'exam_mark_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public static function describe(?array $marks, bool $absent): string
    {
        if ($absent) {
            return __('Absent');
        }

        return collect($marks ?? [])->map(fn ($v, $k) => __(ucfirst($k === 'ca' ? 'CA' : $k)).' '.($v === null ? '—' : (float) $v))->implode(', ') ?: '—';
    }
}
