<?php

namespace App\Models\Learning;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    use Auditable, BelongsToBranch;

    public const STATUSES = ['submitted', 'evaluated', 'returned'];

    protected $fillable = ['text'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime', 'evaluated_at' => 'datetime', 'is_late' => 'boolean', 'marks' => 'decimal:2',
            'assignment_id' => 'integer', 'student_id' => 'integer', 'attempts' => 'integer', 'evaluated_by' => 'integer',
            'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function tone(): string
    {
        return match ($this->status) {
            'evaluated' => 'success', 'returned' => 'warning', default => $this->is_late ? 'danger' : 'primary',
        };
    }
}
