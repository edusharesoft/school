<?php

namespace App\Models\Learning;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use BelongsToBranch;

    public const TYPES = ['mcq_single', 'mcq_multi', 'true_false'];

    protected $fillable = ['position', 'type', 'text', 'options', 'correct', 'marks', 'explanation'];

    protected function casts(): array
    {
        return ['options' => 'array', 'correct' => 'array', 'marks' => 'decimal:2', 'position' => 'integer', 'quiz_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @param  array<int,int|string>  $given */
    public function isCorrect(array $given): bool
    {
        $given = collect($given)->map(fn ($i) => (int) $i)->unique()->sort()->values()->all();
        $correct = collect($this->correct)->map(fn ($i) => (int) $i)->unique()->sort()->values()->all();

        return $given !== [] && $given === $correct;   // all-or-nothing, also for multiple answers
    }
}
