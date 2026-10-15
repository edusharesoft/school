<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Communication\NotificationMessage;
use App\Models\Learning\Quiz;
use App\Models\Learning\QuizAttempt;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Online quizzes: authoring, publishing, timed attempts, automatic grading, answer privacy and access rules. */
class QuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
    }

    protected function world(): array
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $other = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->inBranch($branch, fn () => $setup['sectionA']->forceFill(['class_teacher_id' => $teacher->id])->save());
        $this->inBranch($branch, fn () => $setup['nextSection']->forceFill(['class_teacher_id' => $other->id])->save());
        $subject = $this->inBranch($branch, function () use ($setup) {
            $s = \App\Models\Academic\Subject::query()->create(['name' => 'Mathematics', 'code' => 'MATH', 'type' => 'theory', 'is_active' => true]);
            \App\Models\Academic\ClassSubject::query()->create(['school_class_id' => $setup['class']->id, 'subject_id' => $s->id, 'category' => 'compulsory', 'full_marks' => 100, 'pass_marks' => 33]);

            return $s;
        });
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $c = $this->enrolStudent($branch, $setup, 'Chandni', $setup['nextSection']);
        [$studentA, $parentA] = $this->portalUsers($branch, $admin, $a);
        [$studentC] = $this->portalUsers($branch, $admin, $c);

        return compact('branch', 'setup', 'admin', 'teacher', 'other', 'subject', 'a', 'c', 'studentA', 'parentA', 'studentC');
    }

    protected function portalUsers(Branch $branch, User $admin, Student $student): array
    {
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $guardian = $this->inBranch($branch, fn () => $student->guardians()->first());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();

        return [User::query()->where('username', strtolower($student->student_code))->sole(), User::query()->where('phone', $guardian->phone)->sole()];
    }

    protected function questions(): array
    {
        return [
            ['type' => 'mcq_single', 'text' => 'What is 2 + 2?', 'options' => ['3', '4', '5', ''], 'correct' => [1], 'marks' => 1, 'explanation' => 'Because 2+2=4'],
            ['type' => 'mcq_multi', 'text' => 'Which are prime?', 'options' => ['2', '3', '4', '5'], 'correct' => [0, 1, 3], 'marks' => 3],
            ['type' => 'true_false', 'text' => 'The earth is flat.', 'correct' => [1], 'marks' => 2],
        ];
    }

    protected function payload(array $w, array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Chapter 1 quiz', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id, 'subject_id' => $w['subject']->id,
            'time_limit_minutes' => 20, 'max_attempts' => 1, 'show_answers' => 'after_close', 'pass_percent' => 50, 'questions' => $this->questions(),
        ];
    }

    protected function make(array $w, array $overrides = [], bool $publish = true, ?User $as = null): Quiz
    {
        $this->actingOnBranch($as ?? $w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/quizzes'), $this->payload($w, $overrides) + ($publish ? ['publish_now' => 1] : []))->assertSessionHasNoErrors();

        return $this->inBranch($w['branch'], fn () => Quiz::query()->latest('id')->firstOrFail());
    }

    /** @return array<string,array<int,int>> answers keyed by the question text */
    protected function answersFor(Quiz $quiz, array $byText, Branch $branch): array
    {
        return $this->inBranch($branch, function () use ($quiz, $byText) {
            $out = [];
            foreach ($quiz->questions()->get() as $q) {
                if (isset($byText[$q->text])) {
                    $out[$q->id] = $byText[$q->text];
                }
            }

            return $out;
        });
    }

    protected function attemptOf(Quiz $quiz, Branch $branch, int $no = 1): QuizAttempt
    {
        return $this->inBranch($branch, fn () => QuizAttempt::query()->where('quiz_id', $quiz->id)->where('attempt_no', $no)->firstOrFail());
    }

    protected function startAs(User $user, array $w, Quiz $quiz): QuizAttempt
    {
        $this->actingOnBranch($user, $w['branch'])->post($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}/start"))->assertRedirect();

        return $this->inBranch($w['branch'], fn () => QuizAttempt::query()->where('quiz_id', $quiz->id)->latest('id')->firstOrFail());
    }

    // ------------------------------------------------------------------------------------------------------------

    public function test_teacher_builds_validates_and_publishes_a_quiz_and_families_are_notified(): void
    {
        $w = $this->world();
        $quiz = $this->make($w, publish: false);
        $this->assertSame('draft', $quiz->status);
        $this->assertSame(3, $quiz->questions()->count());
        $this->assertEquals(6.0, $quiz->totalMarks());
        $first = $this->inBranch($w['branch'], fn () => $quiz->questions()->first());
        $this->assertSame(['3', '4', '5'], $first->options, 'blank options are dropped');
        $this->assertSame([1], $first->correct);
        $tf = $this->inBranch($w['branch'], fn () => $quiz->questions()->where('type', 'true_false')->first());
        $this->assertSame(['True', 'False'], $tf->options);

        // a draft is invisible to students
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/quizzes'))->assertOk()->assertDontSee('Chapter 1 quiz');
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}"))->assertNotFound();

        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/quizzes/{$quiz->id}/publish"))->assertSessionHasNoErrors();
        $users = $this->inBranch($w['branch'], fn () => NotificationMessage::query()->where('event', 'quiz.published')->where('channel', 'in_app')->pluck('user_id')->sort()->values()->all());
        $this->assertSame(collect([$w['studentA']->id, $w['parentA']->id])->sort()->values()->all(), $users);

        // validation
        $url = $this->branchUrl($w['branch'], 'teacher/learning/quizzes');
        $post = fn (array $over) => $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, $this->payload($w, $over));
        $q = $this->questions();
        $post(['questions' => []])->assertSessionHasErrors('questions');
        $post(['questions' => [['type' => 'mcq_single', 'text' => 'One option', 'options' => ['a', '', '', ''], 'correct' => [0], 'marks' => 1]]])->assertSessionHasErrors('questions');
        $post(['questions' => [['type' => 'mcq_single', 'text' => 'No answer', 'options' => ['a', 'b'], 'correct' => [], 'marks' => 1]]])->assertSessionHasErrors('questions');
        $post(['questions' => [['type' => 'mcq_single', 'text' => 'Two answers', 'options' => ['a', 'b'], 'correct' => [0, 1], 'marks' => 1]]])->assertSessionHasErrors('questions');
        $post(['questions' => [['type' => 'mcq_single', 'text' => '', 'options' => ['a', 'b'], 'correct' => [0], 'marks' => 1]]])->assertSessionHasErrors('questions');
        $post(['questions' => [['type' => 'true_false', 'text' => 'TF', 'correct' => [], 'marks' => 1]]])->assertSessionHasErrors('questions');
        $post(['school_class_id' => $w['setup']['next']->id, 'section_id' => $w['setup']['nextSection']->id])->assertSessionHasErrors('section_id');
        $post(['section_id' => null])->assertSessionHasErrors('section_id');
        $post(['available_from' => '2026-03-20T10:00', 'available_until' => '2026-03-19T10:00'])->assertSessionHasErrors('available_until');
        $this->assertSame(1, $this->inBranch($w['branch'], fn () => Quiz::query()->count()));

        // a mistake in a multiple-answer question after a blank option keeps the right mapping
        $quiz2 = $this->make($w, ['title' => 'Mapping', 'questions' => [['type' => 'mcq_multi', 'text' => 'Pick', 'options' => ['', 'x', '', 'y'], 'correct' => [1, 3], 'marks' => 1]]], false);
        $mapped = $this->inBranch($w['branch'], fn () => $quiz2->questions()->first());
        $this->assertSame(['x', 'y'], $mapped->options);
        $this->assertSame([0, 1], $mapped->correct);

        // teacher scoping
        $this->actingOnBranch($w['other'], $w['branch'])->get($this->branchUrl($w['branch'], 'teacher/learning/quizzes'))->assertOk()->assertDontSee('Chapter 1 quiz');
        $this->actingOnBranch($w['other'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/quizzes/{$quiz->id}"))->assertNotFound();
        $this->actingOnBranch($w['other'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/quizzes/{$quiz->id}/close"))->assertNotFound();
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], 'learning/quizzes'))->assertOk()->assertSee('Chapter 1 quiz');
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/quizzes/{$quiz->id}"))->assertOk()->assertSee('What is 2 + 2?')->assertSee('Because 2+2=4');
    }

    public function test_a_student_takes_the_quiz_and_it_is_graded_without_leaking_answers(): void
    {
        $w = $this->world();
        $quiz = $this->make($w);

        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/quizzes'))->assertOk()->assertSee('Chapter 1 quiz')->assertSee('Start');
        $attempt = $this->startAs($w['studentA'], $w, $quiz);
        $this->assertSame(1, $attempt->attempt_no);
        $this->assertEquals($attempt->started_at->copy()->addMinutes(20)->timestamp, $attempt->expires_at->timestamp);

        // the answering page shows questions and options but never the correct answers or explanations
        $page = $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}"))->assertOk();
        $page->assertSee('What is 2 + 2?')->assertSee('Which are prime?')->assertDontSee('Because 2+2=4');
        $this->assertStringNotContainsString('"correct"', $page->getContent());

        // autosave keeps answers; unknown questions / out-of-range options are ignored
        $answers = $this->answersFor($quiz, ['What is 2 + 2?' => [1], 'Which are prime?' => [0, 1, 3], 'The earth is flat.' => [1]], $w['branch']);
        $junk = $answers + [999999 => [0], array_key_first($answers) => [1, 99]];
        $this->actingOnBranch($w['studentA'], $w['branch'])->postJson($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}/save"), ['answers' => $junk])->assertOk()->assertJson(['saved' => true]);
        $this->assertSame([1], $this->attemptOf($quiz, $w['branch'])->answers[array_key_first($answers)]);

        // another student cannot touch the attempt
        $this->actingOnBranch($w['studentC'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}"))->assertNotFound();
        $this->actingOnBranch($w['studentC'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}/submit"), ['answers' => $answers])->assertNotFound();

        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}/submit"), ['answers' => $answers])->assertRedirect();
        $done = $this->attemptOf($quiz, $w['branch']);
        $this->assertEquals(6.0, (float) $done->score);
        $this->assertEquals(100.0, (float) $done->percent);
        $this->assertFalse($done->timed_out);

        // answers are hidden while the quiz is open ("after_close"), shown once it is closed
        $result = fn () => $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}/result"))->assertOk();
        $result()->assertSee('6 / 6')->assertSee('100%')->assertSee('Passed')->assertDontSee('Because 2+2=4');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/quizzes/{$quiz->id}/close"))->assertSessionHasNoErrors();
        $result()->assertSee('Because 2+2=4');
        // a finished attempt cannot be answered again, and the attempt page sends the student to the result
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}"))->assertRedirect($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}/result"));
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}/start"))->assertSessionHasErrors('quiz');

        // teacher sees the results
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/quizzes/{$quiz->id}"))->assertOk()->assertSee('Anika')->assertSee('6 / 6')->assertDontSee('Chandni');
    }

    public function test_partial_credit_is_all_or_nothing_per_question_and_the_best_attempt_counts(): void
    {
        $w = $this->world();
        $quiz = $this->make($w, ['max_attempts' => 2, 'time_limit_minutes' => null, 'show_answers' => 'after_submit']);

        $first = $this->startAs($w['studentA'], $w, $quiz);
        // single right (1) + multi missing one prime (0) + true/false wrong (0)
        $answers = $this->answersFor($quiz, ['What is 2 + 2?' => [1], 'Which are prime?' => [0, 1], 'The earth is flat.' => [0]], $w['branch']);
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quiz-attempts/{$first->id}/submit"), ['answers' => $answers])->assertRedirect();
        $a1 = $this->attemptOf($quiz, $w['branch']);
        $this->assertEquals(1.0, (float) $a1->score);
        $this->assertNull($a1->expires_at, 'untimed quiz');
        // answers shown right after the attempt for this quiz
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quiz-attempts/{$first->id}/result"))->assertSee('Because 2+2=4')->assertSee('Failed');

        // second attempt, all right
        $second = $this->startAs($w['studentA'], $w, $quiz);
        $this->assertSame(2, $second->attempt_no);
        $all = $this->answersFor($quiz, ['What is 2 + 2?' => [1], 'Which are prime?' => [0, 1, 3], 'The earth is flat.' => [1]], $w['branch']);
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quiz-attempts/{$second->id}/submit"), ['answers' => $all])->assertRedirect();

        // no third attempt
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}/start"))->assertSessionHasErrors('quiz');
        $this->assertSame(2, $this->inBranch($w['branch'], fn () => QuizAttempt::query()->count()));

        // best attempt: 100% — in the family's view and the progress summary
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/quizzes"))->assertOk()->assertSee('Chapter 1 quiz')->assertSee('100%');
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/learning'))->assertOk()->assertSee('1 / 1 · 100%');
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['c']->id}/quizzes"))->assertNotFound();
        // parents have no way to start or open attempts
        $this->actingOnBranch($w['parentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}/start"))->assertForbidden();
    }

    public function test_the_server_clock_decides_when_time_is_up(): void
    {
        $w = $this->world();
        $quiz = $this->make($w, ['time_limit_minutes' => 10]);
        $attempt = $this->startAs($w['studentA'], $w, $quiz);
        $partial = $this->answersFor($quiz, ['What is 2 + 2?' => [1]], $w['branch']);
        $all = $this->answersFor($quiz, ['What is 2 + 2?' => [1], 'Which are prime?' => [0, 1, 3], 'The earth is flat.' => [1]], $w['branch']);
        $this->actingOnBranch($w['studentA'], $w['branch'])->postJson($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}/save"), ['answers' => $partial])->assertOk();

        // 10 minutes + 5 seconds: inside the grace period, the posted answers count
        $this->travelTo(now()->addMinutes(10)->addSeconds(5));
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}/submit"), ['answers' => $all])->assertRedirect();
        $this->assertEquals(6.0, (float) $this->attemptOf($quiz, $w['branch'])->score);
        $this->assertTrue($this->attemptOf($quiz, $w['branch'])->timed_out, 'handed in after the clock');

        // second quiz: far too late, only the autosaved answers count
        $quiz2 = $this->make($w, ['title' => 'Late quiz', 'time_limit_minutes' => 10]);
        $attempt2 = $this->startAs($w['studentA'], $w, $quiz2);
        $partial2 = $this->answersFor($quiz2, ['What is 2 + 2?' => [1]], $w['branch']);
        $all2 = $this->answersFor($quiz2, ['What is 2 + 2?' => [1], 'Which are prime?' => [0, 1, 3], 'The earth is flat.' => [1]], $w['branch']);
        $this->actingOnBranch($w['studentA'], $w['branch'])->postJson($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt2->id}/save"), ['answers' => $partial2])->assertOk();
        $this->travelTo(now()->addMinutes(11));
        $this->actingOnBranch($w['studentA'], $w['branch'])->postJson($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt2->id}/save"), ['answers' => $all2])->assertOk()->assertJson(['finished' => true]);
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt2->id}/submit"), ['answers' => $all2])->assertRedirect();
        $done = $this->inBranch($w['branch'], fn () => QuizAttempt::query()->where('quiz_id', $quiz2->id)->firstOrFail());
        $this->assertEquals(1.0, (float) $done->score);
        $this->assertTrue($done->timed_out);

        // a student who never comes back: opening the attempt page after the time auto-submits it
        $quiz3 = $this->make($w, ['title' => 'Abandoned', 'time_limit_minutes' => 5]);
        $attempt3 = $this->startAs($w['studentA'], $w, $quiz3);
        $this->travelTo(now()->addMinutes(6));
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt3->id}"))->assertRedirect();
        $this->assertNotNull($this->inBranch($w['branch'], fn () => QuizAttempt::query()->findOrFail($attempt3->id)->submitted_at));
    }

    public function test_availability_window_and_closing(): void
    {
        $w = $this->world();
        $quiz = $this->make($w, ['available_from' => '2026-03-16T09:00', 'available_until' => '2026-03-16T09:30', 'time_limit_minutes' => 60]);

        // not open yet: hidden from the student
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/quizzes'))->assertOk()->assertDontSee('Chapter 1 quiz');
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}/start"))->assertNotFound();

        $this->travelTo(now()->setDate(2026, 3, 16)->setTime(9, 10));
        $attempt = $this->startAs($w['studentA'], $w, $quiz);
        $this->assertEquals($quiz->available_until->timestamp, $attempt->expires_at->timestamp, 'the clock never runs past the closing time');

        // after the closing time nobody can start
        $this->travelTo(now()->setDate(2026, 3, 16)->setTime(9, 45));
        $this->actingOnBranch($w['studentC'], $w['branch'])->post($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}/start"))->assertNotFound();
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}"))->assertOk()->assertSee('The time for this quiz is over');
        // …and the unfinished attempt was handed in automatically
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quiz-attempts/{$attempt->id}"))->assertRedirect();
        $this->assertTrue($this->inBranch($w['branch'], fn () => QuizAttempt::query()->findOrFail($attempt->id)->timed_out));
    }

    public function test_questions_lock_once_students_started_and_access_rules_hold(): void
    {
        $w = $this->world();
        $quiz = $this->make($w);
        $this->startAs($w['studentA'], $w, $quiz);
        $edit = $this->branchUrl($w['branch'], "teacher/learning/quizzes/{$quiz->id}");

        // title and settings can change, questions and class cannot
        $this->actingOnBranch($w['teacher'], $w['branch'])->put($edit, $this->payload($w, ['title' => 'Renamed quiz', 'questions' => [['type' => 'true_false', 'text' => 'New?', 'correct' => [0], 'marks' => 1]]]))->assertSessionHasNoErrors();
        $this->assertSame('Renamed quiz', $quiz->fresh()->title);
        $this->assertSame(3, $this->inBranch($w['branch'], fn () => $quiz->questions()->count()), 'posted questions are ignored once attempts exist');
        $this->actingOnBranch($w['teacher'], $w['branch'])->put($edit, $this->payload($w, ['school_class_id' => $w['setup']['next']->id, 'section_id' => $w['setup']['nextSection']->id]))->assertSessionHasErrors();
        // cannot delete a quiz students have taken
        $this->actingOnBranch($w['admin'], $w['branch'])->delete($this->branchUrl($w['branch'], "learning/quizzes/{$quiz->id}"))->assertForbidden();
        // a draft without attempts can be deleted
        $draft = $this->make($w, ['title' => 'Throwaway'], false);
        $this->actingOnBranch($w['admin'], $w['branch'])->delete($this->branchUrl($w['branch'], "learning/quizzes/{$draft->id}"))->assertRedirect();
        $this->assertNull($this->inBranch($w['branch'], fn () => Quiz::query()->find($draft->id)));

        // students and parents have no authoring screens
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'learning/quizzes'))->assertForbidden();
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'teacher/learning/quizzes'))->assertForbidden();
        $this->actingOnBranch($w['parentA'], $w['branch'])->post($this->branchUrl($w['branch'], 'learning/quizzes'), $this->payload($w))->assertForbidden();
        // a student of another class cannot see or start it
        $this->actingOnBranch($w['studentC'], $w['branch'])->get($this->branchUrl($w['branch'], "student/quizzes/{$quiz->id}"))->assertNotFound();
        // another branch cannot reach it at all
        $branch2 = $this->makeBranch(null);
        $admin2 = $this->branchUser($branch2, 'branch-admin');
        $this->actingOnBranch($admin2, $branch2)->get($this->branchUrl($branch2, "learning/quizzes/{$quiz->id}"))->assertNotFound();
    }

    public function test_learning_api_serves_students_and_guardians_without_leaking_links_early(): void
    {
        $w = $this->world();
        $teacherPost = fn (string $path, array $data) => $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/'.$path), $data)->assertSessionHasNoErrors();
        $teacherPost('assignments', ['kind' => 'homework', 'title' => 'API homework', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id,
            'due_at' => '2026-03-18 17:00', 'max_marks' => 10, 'submission_mode' => 'text', 'publish_now' => 1]);
        $teacherPost('online-classes', ['mode' => 'online', 'provider' => 'manual', 'title' => 'API class', 'join_url' => 'https://meet.example.com/api', 'passcode' => '777',
            'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id, 'starts_at' => '2026-03-16T10:00', 'ends_at' => '2026-03-16T10:45']);
        $teacherPost('materials', ['type' => 'link', 'title' => 'API video', 'url' => 'https://video.example.com/1', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id, 'is_published' => 1]);
        $this->make($w, ['title' => 'API quiz']);

        $this->flushSession();
        $this->app['auth']->forgetGuards();   // the page requests above signed the teacher in; the API calls must use tokens only
        $host = fn ($path) => $this->branchUrl($w['branch'], 'api/v1/'.$path);
        $login = fn (User $u, string $name) => $this->postJson($host('auth/login'), ['login' => $name, 'password' => 'secret123', 'device_name' => 'phpunit'])->assertOk()->json('data.token');
        $studentToken = $login($w['studentA'], $w['studentA']->username);
        $this->app['auth']->forgetGuards();
        $parentToken = $login($w['parentA'], $w['parentA']->phone);
        $this->app['auth']->forgetGuards();

        // student: own data, no meeting link a day before the class
        $r = $this->withToken($studentToken)->getJson($host('my/online-classes'))->assertOk()->assertJsonPath('data.0.student.name', 'Anika')->assertJsonPath('data.0.items.0.title', 'API class')->assertJsonPath('data.0.items.0.join_url', null)->assertJsonPath('data.0.items.0.passcode', null);
        $this->assertStringNotContainsString('meet.example.com', $r->getContent());
        $this->withToken($studentToken)->getJson($host('my/assignments'))->assertOk()->assertJsonPath('data.0.items.0.title', 'API homework')->assertJsonPath('data.0.items.0.submission', null);
        $this->withToken($studentToken)->getJson($host('my/materials'))->assertOk()->assertJsonPath('data.0.items.0.url', 'https://video.example.com/1');
        $q = $this->withToken($studentToken)->getJson($host('my/quizzes'))->assertOk()->assertJsonPath('data.0.items.0.title', 'API quiz')->assertJsonPath('data.0.items.0.questions', 3)->assertJsonPath('data.0.items.0.best', null);
        $this->assertStringNotContainsString('Because 2+2=4', $q->getContent());
        $this->withToken($studentToken)->getJson($host('my/learning'))->assertOk()->assertJsonPath('data.0.assignments.total', 1)->assertJsonPath('data.0.quizzes.total', 1);

        // from 15 minutes before the start the link appears
        $this->travelTo(now()->setDate(2026, 3, 16)->setTime(9, 50));
        $this->withToken($studentToken)->getJson($host('my/online-classes'))->assertOk()->assertJsonPath('data.0.items.0.join_url', 'https://meet.example.com/api')->assertJsonPath('data.0.items.0.passcode', '777');

        // guardian: the child's data
        $this->app['auth']->forgetGuards();
        $this->withToken($parentToken)->getJson($host('auth/me'))->assertOk()->assertJsonPath('data.portal', 'parent');
        $this->app['auth']->forgetGuards();
        $this->withToken($parentToken)->getJson($host('my/assignments'))->assertOk()->assertJsonPath('data.0.student.name', 'Anika')->assertJsonPath('data.0.items.0.title', 'API homework');
        // staff accounts are not students or guardians
        $this->app['auth']->forgetGuards();
        $adminToken = $this->postJson($host('auth/login'), ['login' => $w['admin']->email, 'password' => 'password', 'device_name' => 'phpunit'])->assertOk()->json('data.token');
        $this->withToken($adminToken)->getJson($host('my/quizzes'))->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders(); // withToken() sticks to the test instance
        $this->getJson($host('my/quizzes'))->assertUnauthorized();
    }
}
