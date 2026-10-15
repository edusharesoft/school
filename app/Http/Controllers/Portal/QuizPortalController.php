<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Learning\Quiz;
use App\Models\Learning\QuizAttempt;
use App\Models\Students\Student;
use App\Services\Learning\QuizService;
use App\Services\Portal\PortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quizzes in the student portal (take, resume, see the result) and the parent portal (follow the scores).
 * The page that shows questions to a student never contains the correct answers.
 */
class QuizPortalController extends Controller
{
    public function __construct(protected PortalService $portal, protected QuizService $quizzes) {}

    // ------------------------------------------------------------ student

    public function index(Request $request): View
    {
        return $this->listPage($this->student($request), 'portal.student.', 'student');
    }

    public function show(Request $request, Quiz $quiz): View
    {
        $student = $this->student($request);
        $this->assertVisible($student, $quiz);
        $open = $this->quizzes->openAttempt($quiz, $student);

        return view('portal.learning.quiz', [
            'quiz' => $quiz->load(['subject:id,name,name_bn', 'schoolClass:id,name'])->loadCount('questions'),
            'student' => $student, 'attempts' => $quiz->attempts()->where('student_id', $student->id)->orderBy('attempt_no')->get(),
            'open' => $open, 'blocker' => $open ? null : $this->quizzes->blocker($quiz, $student), 'total' => $quiz->totalMarks(),
        ]);
    }

    public function start(Request $request, Quiz $quiz): RedirectResponse
    {
        $student = $this->student($request);
        $this->assertVisible($student, $quiz);
        $attempt = $this->quizzes->start($quiz, $student);

        return redirect()->route('portal.student.quizzes.attempt', $attempt);
    }

    public function attempt(Request $request, QuizAttempt $attempt): View|RedirectResponse
    {
        $student = $this->student($request);
        $this->assertOwnAttempt($student, $attempt);
        $quiz = $attempt->quiz;
        if ($attempt->isFinished()) {
            return redirect()->route('portal.student.quizzes.result', $attempt);
        }
        if ($attempt->isExpired()) {
            $this->quizzes->openAttempt($quiz, $student);   // auto-submits what was saved

            return redirect()->route('portal.student.quizzes.result', $attempt)->with('warning', __('Time is over — your saved answers were handed in.'));
        }

        $questions = $quiz->questions()->get()->keyBy('id');
        $ordered = collect($attempt->question_order)->map(fn ($id) => $questions->get($id))->filter()->values();

        return view('portal.learning.quiz-attempt', [
            'quiz' => $quiz, 'attempt' => $attempt, 'questions' => $ordered->map(fn ($q) => [
                'id' => $q->id, 'type' => $q->type, 'text' => $q->text, 'options' => $q->options, 'marks' => $q->marks + 0,
            ])->all(),
            'saved' => $attempt->answers ?? [],
            'secondsLeft' => $attempt->expires_at ? max(0, (int) now()->diffInSeconds($attempt->expires_at, false)) : null,
        ]);
    }

    /** Autosave while answering (JSON). */
    public function save(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $student = $this->student($request);
        $this->assertOwnAttempt($student, $attempt);
        $this->quizzes->saveProgress($attempt, (array) $request->input('answers', []));

        return response()->json(['saved' => true, 'finished' => $attempt->isFinished() || $attempt->isExpired()]);
    }

    public function submit(Request $request, QuizAttempt $attempt): RedirectResponse
    {
        $student = $this->student($request);
        $this->assertOwnAttempt($student, $attempt);
        $this->quizzes->submit($attempt, (array) $request->input('answers', []));

        return redirect()->route('portal.student.quizzes.result', $attempt)->with('success', __('Quiz handed in.'));
    }

    public function result(Request $request, QuizAttempt $attempt): View|RedirectResponse
    {
        $student = $this->student($request);
        $this->assertOwnAttempt($student, $attempt);
        if (! $attempt->isFinished()) {
            return redirect()->route('portal.student.quizzes.attempt', $attempt);
        }
        $quiz = $attempt->quiz->load('questions');
        $reveal = $quiz->revealsAnswers();

        return view('portal.learning.quiz-result', [
            'quiz' => $quiz, 'attempt' => $attempt, 'reveal' => $reveal,
            'review' => $quiz->questions->map(fn ($q) => [
                'question' => $q, 'given' => $attempt->answers[$q->id] ?? [], 'right' => $q->isCorrect($attempt->answers[$q->id] ?? []),
            ]),
            'passed' => $quiz->pass_percent !== null ? (float) $attempt->percent >= (float) $quiz->pass_percent : null,
            'canRetry' => $this->quizzes->blocker($quiz, $student) === null,
        ]);
    }

    // ------------------------------------------------------------ parent

    public function parentIndex(Request $request, Student $student): View
    {
        $guardian = $this->portal->guardianFor($request->user());
        abort_unless($guardian && $this->portal->isChildOf($guardian, $student), 404);

        return $this->listPage($student, 'portal.parent.', 'parent');
    }

    // ------------------------------------------------------------ shared

    protected function listPage(Student $student, string $routePrefix, string $mode): View
    {
        $enrollment = $student->currentEnrollment;
        $quizzes = $enrollment
            ? Quiz::query()->visibleToStudents()->forEnrollment($enrollment)->with(['subject:id,name,name_bn'])->withCount('questions')->latest('id')->get()
            : collect();
        $attempts = $quizzes->isEmpty() ? collect() : QuizAttempt::query()->where('student_id', $student->id)->whereIn('quiz_id', $quizzes->pluck('id'))->whereNotNull('submitted_at')->get()->groupBy('quiz_id');

        return view('portal.learning.quizzes', [
            'student' => $student, 'mode' => $mode, 'routePrefix' => $routePrefix, 'routeParams' => $mode === 'parent' ? [$student] : [],
            'rows' => $quizzes->map(fn (Quiz $q) => [
                'quiz' => $q, 'attempts' => $attempts->get($q->id, collect()), 'best' => ($attempts->get($q->id) ?? collect())->sortByDesc('percent')->first(),
                'blocker' => $mode === 'student' ? $this->quizzes->blocker($q, $student) : null,
            ]),
        ]);
    }

    protected function assertVisible(Student $student, Quiz $quiz): void
    {
        $enrollment = $student->currentEnrollment;
        abort_unless($enrollment && Quiz::query()->visibleToStudents()->forEnrollment($enrollment)->whereKey($quiz->id)->exists(), 404);
    }

    protected function assertOwnAttempt(Student $student, QuizAttempt $attempt): void
    {
        abort_unless($attempt->student_id === $student->id, 404);
    }

    protected function student(Request $request): Student
    {
        $student = $this->portal->studentFor($request->user());
        abort_unless($student, 403, __('Your login is not linked to a student record. Please contact the school office.'));

        return $student;
    }
}
