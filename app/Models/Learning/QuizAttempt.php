<?php

namespace App\Models\Learning;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Students\Student;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use BelongsToBranch;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'expires_at' => 'datetime', 'submitted_at' => 'datetime', 'question_order' => 'array', 'answers' => 'array',
            'score' => 'decimal:2', 'max_score' => 'decimal:2', 'percent' => 'decimal:2', 'timed_out' => 'boolean',
            'quiz_id' => 'integer', 'student_id' => 'integer', 'attempt_no' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isFinished(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
