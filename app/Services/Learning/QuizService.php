<?php

namespace App\Services\Learning;

use App\Models\Academic\AcademicSession;
use App\Models\Learning\Quiz;
use App\Models\Learning\QuizAttempt;
use App\Models\Learning\QuizQuestion;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Online quizzes: authoring (questions are locked once a student has started), publishing (students and guardians are
 * told), attempts with a server-side clock, automatic grading (all-or-nothing per question) and results.
 * The correct answers never leave the server while a student is answering.
 */
class QuizService
{
    /** Seconds a late submit is still accepted after the clock ran out (network delay). */
    public const GRACE = 30;

    public function __construct(protected LearningAccess $access, protected NotificationHub $hub, protected RecipientResolver $recipients) {}

    /**
     * @param  array<int,array{type:string,text:string,options?:array,correct?:array,marks?:numeric,explanation?:?string}>|null  $questions  null = keep the questions as they are
     */
    public function save(array $data, ?array $questions, User $user, ?Quiz $quiz = null): Quiz
    {
        $classId = (int) $data['school_class_id'];
        $sectionId = filled($data['section_id'] ?? null) ? (int) $data['section_id'] : null;
        $subjectId = filled($data['subject_id'] ?? null) ? (int) $data['subject_id'] : null;

        if (! $this->access->canTeach($user, $classId, $sectionId)) {
            throw ValidationException::withMessages(['section_id' => __('You can only give quizzes to the sections you teach.')]);
        }
        if (! $this->access->subjectBelongsToClass($classId, $subjectId)) {
            throw ValidationException::withMessages(['subject_id' => __('This subject is not taught in the chosen class.')]);
        }
        if ($quiz && ! $this->access->manages($user, $quiz)) {
            abort(403);
        }
        $started = $quiz?->exists && $quiz->attempts()->exists();
        if ($started && ($classId !== $quiz->school_class_id || $sectionId !== $quiz->section_id)) {
            throw ValidationException::withMessages(['school_class_id' => __('The class cannot be changed after students have started the quiz.')]);
        }
        if ($started && $questions !== null) {
            throw ValidationException::withMessages(['questions' => __('Questions cannot be changed after students have started the quiz.')]);
        }
        $clean = $questions === null ? null : $this->cleanQuestions($questions);
        if (! $quiz?->exists && ($clean === null || $clean === [])) {
            throw ValidationException::withMessages(['questions' => __('Add at least one question.')]);
        }
        if ($questions !== null && $clean === []) {
            throw ValidationException::withMessages(['questions' => __('Add at least one question.')]);
        }

        $session = AcademicSession::query()->current()->first();
        if (! $quiz?->exists && ! $session) {
            throw ValidationException::withMessages(['school_class_id' => __('There is no current academic session. Set one under Academics first.')]);
        }

        return DB::transaction(function () use ($data, $clean, $user, $quiz, $session) {
            $quiz ??= new Quiz;
            $quiz->fill($data);
            $quiz->time_limit_minutes = filled($data['time_limit_minutes'] ?? null) ? (int) $data['time_limit_minutes'] : null;
            $quiz->max_attempts = max(1, (int) ($data['max_attempts'] ?? 1));
            $quiz->shuffle_questions = ! empty($data['shuffle_questions']);
            $quiz->show_answers = $data['show_answers'] ?? 'after_close';
            if (! $quiz->exists) {
                $quiz->forceFill(['academic_session_id' => $session->id, 'created_by' => $user->id, 'status' => 'draft']);
            }
            $quiz->save();

            if ($clean !== null) {
                $quiz->questions()->delete();
                foreach ($clean as $i => $q) {
                    $quiz->questions()->create(['position' => $i + 1] + $q);
                }
            }

            return $quiz;
        });
    }

    public function publish(Quiz $quiz, User $user): Quiz
    {
        if ($quiz->status !== 'draft') {
            throw ValidationException::withMessages(['status' => __('Only a draft can be published.')]);
        }
        if (! $quiz->questions()->exists()) {
            throw ValidationException::withMessages(['status' => __('Add at least one question.')]);
        }
        if ($quiz->available_until && $quiz->available_until->isPast()) {
            throw ValidationException::withMessages(['available_until' => __('The quiz closing time has already passed. Change it first.')]);
        }
        $quiz->forceFill(['status' => 'published', 'published_at' => now()])->save();
        $this->announce($quiz, $user);

        return $quiz;
    }

    public function close(Quiz $quiz): Quiz
    {
        if ($quiz->status === 'published') {
            $quiz->forceFill(['status' => 'closed'])->save();
        }

        return $quiz;
    }

    public function reopen(Quiz $quiz): Quiz
    {
        if ($quiz->status === 'closed') {
            $quiz->forceFill(['status' => 'published'])->save();
        }

        return $quiz;
    }

    // ---- attempts ---------------------------------------------------------------------------------------------

    /** Why this student cannot start (another) attempt, or null when they can. */
    public function blocker(Quiz $quiz, Student $student): ?string
    {
        if ($quiz->status !== 'published') {
            return __('This quiz is closed.');
        }
        if ($quiz->available_from && $quiz->available_from->isFuture()) {
            return __('This quiz opens on :time.', ['time' => $quiz->available_from->format('d M Y H:i')]);
        }
        if ($quiz->available_until && $quiz->available_until->isPast()) {
            return __('The time for this quiz is over.');
        }
        $used = $quiz->attempts()->where('student_id', $student->id)->count();
        if ($used >= $quiz->max_attempts) {
            return __('You have used all :n attempt(s).', ['n' => $quiz->max_attempts]);
        }

        return null;
    }

    /** The unfinished attempt of the student (auto-submitting it if its time ran out), or null. */
    public function openAttempt(Quiz $quiz, Student $student): ?QuizAttempt
    {
        $attempt = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('student_id', $student->id)->whereNull('submitted_at')->latest('id')->first();
        if ($attempt && $attempt->isExpired()) {
            $this->grade($attempt->setRelation('quiz', $quiz), $attempt->answers ?? [], true);

            return null;
        }

        return $attempt;
    }

    /** Start a new attempt, or resume the unfinished one. */
    public function start(Quiz $quiz, Student $student): QuizAttempt
    {
        return DB::transaction(function () use ($quiz, $student) {
            // serialise concurrent starts of the same student
            Quiz::query()->whereKey($quiz->id)->lockForUpdate()->first();

            if ($open = $this->openAttempt($quiz, $student)) {
                return $open;
            }
            if ($reason = $this->blocker($quiz, $student)) {
                throw ValidationException::withMessages(['quiz' => $reason]);
            }

            $ids = $quiz->questions()->pluck('id')->map(fn ($i) => (int) $i)->all();
            if ($quiz->shuffle_questions) {
                shuffle($ids);
            }
            $expires = $quiz->time_limit_minutes ? now()->addMinutes($quiz->time_limit_minutes) : null;
            if ($quiz->available_until && (! $expires || $quiz->available_until->lt($expires))) {
                $expires = $quiz->available_until->copy();   // nobody may keep answering after the quiz closes
            }

            return QuizAttempt::query()->create([
                'quiz_id' => $quiz->id, 'student_id' => $student->id, 'attempt_no' => $quiz->attempts()->where('student_id', $student->id)->count() + 1,
                'started_at' => now(), 'expires_at' => $expires, 'question_order' => $ids, 'answers' => [],
            ]);
        });
    }

    /** Keep the answers given so far (autosave). Ignored once the attempt is finished or expired. */
    public function saveProgress(QuizAttempt $attempt, array $answers): void
    {
        if ($attempt->isFinished() || $attempt->isExpired()) {
            return;
        }
        $attempt->forceFill(['answers' => $this->normalise($attempt, $answers)])->save();
    }

    /** Hand in the attempt and grade it. Answers sent after the clock (plus a short grace) are ignored; the saved ones count. */
    public function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        if ($attempt->isFinished()) {
            return $attempt;
        }
        $late = $attempt->expires_at && now()->gt($attempt->expires_at->copy()->addSeconds(self::GRACE));

        return $this->grade($attempt, $late ? ($attempt->answers ?? []) : $answers, $attempt->isExpired());
    }

    /** @param  array<int|string,mixed>  $answers  question id => option index / indexes */
    public function grade(QuizAttempt $attempt, array $answers, bool $timedOut = false): QuizAttempt
    {
        $questions = QuizQuestion::query()->where('quiz_id', $attempt->quiz_id)->get()->keyBy('id');
        $answers = $this->normalise($attempt, $answers);
        $score = 0.0;
        $max = 0.0;
        foreach ($attempt->question_order as $qid) {
            $q = $questions->get($qid);
            if (! $q) {
                continue;
            }
            $max += (float) $q->marks;
            if ($q->isCorrect($answers[$qid] ?? [])) {
                $score += (float) $q->marks;
            }
        }
        $attempt->forceFill([
            'answers' => $answers, 'submitted_at' => now(), 'score' => round($score, 2), 'max_score' => round($max, 2),
            'percent' => $max > 0 ? round($score / $max * 100, 2) : 0, 'timed_out' => $timedOut,
        ])->save();

        return $attempt;
    }

    /** Best finished attempt of a student (highest percent, earliest on a tie). */
    public function best(Quiz $quiz, Student $student): ?QuizAttempt
    {
        return $quiz->attempts()->where('student_id', $student->id)->whereNotNull('submitted_at')->orderByDesc('percent')->orderBy('id')->first();
    }

    /**
     * Teacher overview: per student the best result and number of attempts, plus how many got each question right.
     *
     * @return array{rows:Collection, questions:Collection, average:?float, taken:int}
     */
    public function results(Quiz $quiz): array
    {
        $attempts = $quiz->attempts()->whereNotNull('submitted_at')->get();
        $best = $attempts->groupBy('student_id')->map(fn (Collection $a) => $a->sortByDesc('percent')->first());
        $count = $attempts->groupBy('student_id')->map->count();

        $rows = $quiz->audience()->with('currentEnrollment')->orderBy('name')->get()->map(fn (Student $s) => [
            'student' => $s, 'best' => $best->get($s->id), 'attempts' => (int) $count->get($s->id, 0),
        ])->sortBy(fn ($r) => sprintf('%05d-%s', $r['student']->currentEnrollment?->roll_no ?? 99999, $r['student']->name))->values();

        $questions = $quiz->questions()->get()->map(function (QuizQuestion $q) use ($best) {
            $answered = $best->filter(fn ($a) => array_key_exists($q->id, $a->answers ?? []) && ($a->answers[$q->id] ?? []) !== []);
            $right = $best->filter(fn ($a) => $q->isCorrect($a->answers[$q->id] ?? []))->count();

            return ['question' => $q, 'answered' => $answered->count(), 'correct' => $right, 'total' => $best->count()];
        });

        return ['rows' => $rows, 'questions' => $questions, 'average' => $best->isEmpty() ? null : round($best->avg(fn ($a) => (float) $a->percent), 1), 'taken' => $best->count()];
    }

    // ---- internals --------------------------------------------------------------------------------------------

    /** Keep only known questions, valid option indexes, de-duplicated. @return array<int,array<int,int>> */
    protected function normalise(QuizAttempt $attempt, array $answers): array
    {
        $questions = QuizQuestion::query()->where('quiz_id', $attempt->quiz_id)->get()->keyBy('id');
        $out = [];
        foreach ($answers as $qid => $given) {
            $q = $questions->get((int) $qid);
            if (! $q) {
                continue;
            }
            $given = collect(is_array($given) ? $given : [$given])->filter(fn ($i) => is_numeric($i))->map(fn ($i) => (int) $i)
                ->filter(fn ($i) => $i >= 0 && $i < count($q->options))->unique()->values();
            if ($q->type !== 'mcq_multi') {
                $given = $given->take(1);
            }
            if ($given->isNotEmpty()) {
                $out[$q->id] = $given->all();
            }
        }

        return $out;
    }

    /** @return array<int,array{type:string,text:string,options:array,correct:array,marks:float,explanation:?string}> */
    protected function cleanQuestions(array $questions): array
    {
        $clean = [];
        foreach (array_values($questions) as $i => $q) {
            $n = $i + 1;
            $type = $q['type'] ?? null;
            $text = trim((string) ($q['text'] ?? ''));
            if (! in_array($type, QuizQuestion::TYPES, true)) {
                throw ValidationException::withMessages(['questions' => __('Question :n: choose a type.', ['n' => $n])]);
            }
            if ($text === '') {
                throw ValidationException::withMessages(['questions' => __('Question :n: write the question.', ['n' => $n])]);
            }
            $marks = (float) ($q['marks'] ?? 1);
            if ($marks <= 0 || $marks > 1000) {
                throw ValidationException::withMessages(['questions' => __('Question :n: marks must be above zero.', ['n' => $n])]);
            }
            if ($type === 'true_false') {
                $options = ['True', 'False'];
            } else {
                // Blank options are dropped; the correct indexes are re-mapped to the remaining ones.
                $raw = array_values((array) ($q['options'] ?? []));
                $map = [];
                $options = [];
                foreach ($raw as $k => $opt) {
                    if (trim((string) $opt) !== '') {
                        $map[$k] = count($options);
                        $options[] = mb_substr(trim((string) $opt), 0, 500);
                    }
                }
                if (count($options) < 2 || count($options) > 8) {
                    throw ValidationException::withMessages(['questions' => __('Question :n: give between 2 and 8 options.', ['n' => $n])]);
                }
                $q['correct'] = collect((array) ($q['correct'] ?? []))->map(fn ($c) => $map[(int) $c] ?? null)->filter(fn ($c) => $c !== null)->all();
            }
            $correct = collect((array) ($q['correct'] ?? []))->map(fn ($c) => (int) $c)->filter(fn ($c) => $c >= 0 && $c < count($options))->unique()->sort()->values()->all();
            if ($correct === []) {
                throw ValidationException::withMessages(['questions' => __('Question :n: mark the correct answer.', ['n' => $n])]);
            }
            if ($type !== 'mcq_multi' && count($correct) !== 1) {
                throw ValidationException::withMessages(['questions' => __('Question :n: only one answer can be correct.', ['n' => $n])]);
            }
            $clean[] = [
                'type' => $type, 'text' => mb_substr($text, 0, 2000), 'options' => $options, 'correct' => $correct, 'marks' => round($marks, 2),
                'explanation' => filled($q['explanation'] ?? null) ? mb_substr(trim((string) $q['explanation']), 0, 1000) : null,
            ];
        }

        return $clean;
    }

    protected function announce(Quiz $quiz, User $user): void
    {
        $quiz->loadMissing(['subject:id,name,name_bn', 'schoolClass:id,name']);
        $students = $quiz->audience()->with(['user:id,locale'])->get();
        $vars = [
            'title' => $quiz->title, 'subject' => $quiz->subject?->displayName() ?? '—', 'class' => $quiz->schoolClass?->name,
            'until' => $quiz->available_until ? $quiz->available_until->format('d M Y H:i') : '—',
            'time_limit' => $quiz->time_limit_minutes ? $quiz->time_limit_minutes.' '.__('minutes') : __('No time limit'),
        ];
        $recipients = collect();
        foreach ($students as $student) {
            $recipients->push($this->recipients->student($student, $vars));
        }
        $recipients = $recipients->merge($this->recipients->guardiansOf($students, $vars)->values());

        $this->hub->notify('quiz.published', $recipients, $vars, $quiz, [
            'url' => tenant()->branch()?->url('/student/quizzes/'.$quiz->id), 'user' => $user,
        ]);
    }
}
