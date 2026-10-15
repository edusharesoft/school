<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Communication\NotificationMessage;
use App\Models\Learning\Assignment;
use App\Models\Learning\AssignmentSubmission;
use App\Models\Learning\LearningMaterial;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
        Storage::fake('local');
    }

    /** @return array{branch:Branch, setup:array, admin:User, teacher:User, other:User, a:Student, c:Student, studentA:User, parentA:User, studentC:User, parentC:User} */
    protected function world(): array
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $other = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->inBranch($branch, fn () => $setup['sectionA']->forceFill(['class_teacher_id' => $teacher->id])->save());
        $this->inBranch($branch, fn () => $setup['nextSection']->forceFill(['class_teacher_id' => $other->id])->save());
        // the subject must belong to the class
        $this->inBranch($branch, fn () => \App\Models\Academic\ClassSubject::query()->create(['school_class_id' => $setup['class']->id, 'subject_id' => $this->subject()->id, 'category' => 'compulsory', 'full_marks' => 100, 'pass_marks' => 33]));

        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $c = $this->enrolStudent($branch, $setup, 'Chandni', $setup['nextSection']);
        [$studentA, $parentA] = $this->portalUsers($branch, $admin, $a);
        [$studentC, $parentC] = $this->portalUsers($branch, $admin, $c);

        return compact('branch', 'setup', 'admin', 'teacher', 'other', 'a', 'c', 'studentA', 'parentA', 'studentC', 'parentC');
    }

    protected function subject(): \App\Models\Academic\Subject
    {
        return \App\Models\Academic\Subject::query()->firstOrCreate(['code' => 'MATH'], ['name' => 'Mathematics', 'type' => 'theory', 'is_active' => true]);
    }

    protected function portalUsers(Branch $branch, User $admin, Student $student): array
    {
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $guardian = $this->inBranch($branch, fn () => $student->guardians()->first());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();

        return [User::query()->where('username', strtolower($student->student_code))->sole(), User::query()->where('phone', $guardian->phone)->sole()];
    }

    protected function give(array $w, array $overrides = [], ?User $as = null): Assignment
    {
        $this->actingOnBranch($as ?? $w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/assignments'), $overrides + [
            'kind' => 'homework', 'title' => 'Chapter 3 exercises', 'instructions' => 'Solve 1 to 10.',
            'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id, 'subject_id' => $this->inBranch($w['branch'], fn () => $this->subject()->id),
            'due_at' => '2026-03-18 17:00', 'max_marks' => 10, 'submission_mode' => 'either', 'publish_now' => 1,
        ])->assertSessionHasNoErrors();

        return $this->inBranch($w['branch'], fn () => Assignment::query()->latest('id')->firstOrFail());
    }

    public function test_teacher_gives_homework_to_own_section_and_students_and_guardians_are_notified(): void
    {
        $w = $this->world();
        $hw = $this->give($w);

        $this->assertSame('published', $hw->status);
        $users = $this->inBranch($w['branch'], fn () => NotificationMessage::query()->where('event', 'assignment.published')->where('channel', 'in_app')->pluck('user_id')->sort()->values()->all());
        $this->assertSame(collect([$w['studentA']->id, $w['parentA']->id])->sort()->values()->all(), $users);

        // a teacher cannot give work to a section she does not teach
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/assignments'), [
            'kind' => 'homework', 'title' => 'Not mine', 'school_class_id' => $w['setup']['next']->id, 'section_id' => $w['setup']['nextSection']->id,
            'due_at' => '2026-03-18 17:00', 'submission_mode' => 'text',
        ])->assertSessionHasErrors('section_id');
        // …nor omit the section
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/assignments'), [
            'kind' => 'homework', 'title' => 'Whole class', 'school_class_id' => $w['setup']['class']->id,
            'due_at' => '2026-03-18 17:00', 'submission_mode' => 'text',
        ])->assertSessionHasErrors('section_id');
        // subject must be taught in the class
        $other = $this->inBranch($w['branch'], fn () => \App\Models\Academic\Subject::query()->create(['name' => 'Art', 'code' => 'ART', 'type' => 'theory', 'is_active' => true]));
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/assignments'), [
            'kind' => 'homework', 'title' => 'Wrong subject', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id,
            'subject_id' => $other->id, 'due_at' => '2026-03-18 17:00', 'submission_mode' => 'text',
        ])->assertSessionHasErrors('subject_id');

        // lists
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], 'teacher/learning/assignments'))->assertOk()->assertSee('Chapter 3 exercises');
        $this->actingOnBranch($w['other'], $w['branch'])->get($this->branchUrl($w['branch'], 'teacher/learning/assignments'))->assertOk()->assertDontSee('Chapter 3 exercises');
        $this->actingOnBranch($w['other'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}"))->assertNotFound();
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], 'learning/assignments'))->assertOk()->assertSee('Chapter 3 exercises');
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], "learning/assignments/{$hw->id}"))->assertOk()->assertSee('Anika');
    }

    public function test_student_hands_in_teacher_evaluates_and_family_sees_the_result(): void
    {
        $w = $this->world();
        $hw = $this->give($w);

        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/learning'))->assertOk()->assertSee('Chapter 3 exercises');
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/assignments/{$hw->id}"))->assertOk()->assertSee('Solve 1 to 10.');

        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$hw->id}/submit"), [])->assertSessionHasErrors('text');
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$hw->id}/submit"), [
            'text' => 'x = 4', 'file' => UploadedFile::fake()->create('answers.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $sub = $this->inBranch($w['branch'], fn () => AssignmentSubmission::query()->where('assignment_id', $hw->id)->sole());
        $this->assertFalse($sub->is_late);
        Storage::disk('local')->assertExists($sub->file_path);

        // teacher sees it and downloads the file
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}"))->assertOk()->assertSee('answers.pdf')->assertSee('x = 4');
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/submissions/{$sub->id}/file"))->assertOk();

        // evaluate
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/evaluate"), [
            'rows' => [$w['a']->id => ['marks' => 11, 'action' => 'evaluate']],
        ])->assertSessionHasErrors("rows.{$w['a']->id}.marks");
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/evaluate"), [
            'rows' => [$w['a']->id => ['marks' => 8.5, 'feedback' => 'Well done', 'action' => 'skip']],   // typed values are saved even on "no change"
        ])->assertSessionHasNoErrors();
        $sub->refresh();
        $this->assertSame('evaluated', $sub->status);
        $this->assertEquals(8.5, (float) $sub->marks);

        $notified = $this->inBranch($w['branch'], fn () => NotificationMessage::query()->where('event', 'assignment.evaluated')->where('channel', 'in_app')->pluck('user_id')->sort()->values()->all());
        $this->assertSame(collect([$w['studentA']->id, $w['parentA']->id])->sort()->values()->all(), $notified);

        // saving again does not notify twice
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/evaluate"), [
            'rows' => [$w['a']->id => ['marks' => 9, 'feedback' => 'Well done', 'action' => 'evaluate']],
        ]);
        $this->assertSame(2, $this->inBranch($w['branch'], fn () => NotificationMessage::query()->where('event', 'assignment.evaluated')->where('channel', 'in_app')->count()));

        // evaluated work is final for the student
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$hw->id}/submit"), ['text' => 'again'])->assertSessionHasErrors('text');
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/assignments/{$hw->id}"))->assertOk()->assertSee('Well done')->assertSee('9 / 10');

        // the parent follows the child
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/learning"))->assertOk()->assertSee('Chapter 3 exercises');
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/assignments/{$hw->id}"))->assertOk()->assertSee('Well done');
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/assignments/{$hw->id}/submission-file"))->assertOk();
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['c']->id}/learning"))->assertNotFound();   // not her child
        $this->actingOnBranch($w['parentC'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/assignments/{$hw->id}"))->assertNotFound();
    }

    public function test_other_classes_and_roles_are_kept_out(): void
    {
        $w = $this->world();
        $hw = $this->give($w);
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$hw->id}/submit"), ['text' => 'mine'])->assertSessionHasNoErrors();
        $sub = $this->inBranch($w['branch'], fn () => AssignmentSubmission::query()->sole());

        // a student of another class sees nothing and cannot hand in
        $this->actingOnBranch($w['studentC'], $w['branch'])->get($this->branchUrl($w['branch'], "student/assignments/{$hw->id}"))->assertNotFound();
        $this->actingOnBranch($w['studentC'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$hw->id}/submit"), ['text' => 'x'])->assertNotFound();
        $this->actingOnBranch($w['studentC'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/learning'))->assertOk()->assertDontSee('Chapter 3 exercises');
        // portal users cannot open the back-office or teacher screens
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'learning/assignments'))->assertForbidden();
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'teacher/learning/assignments'))->assertForbidden();
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'learning/assignments'))->assertForbidden();
        // the other teacher cannot evaluate or open the file
        $this->actingOnBranch($w['other'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/evaluate"), ['rows' => [$w['a']->id => ['marks' => 1, 'action' => 'evaluate']]])->assertForbidden();
        $this->actingOnBranch($w['other'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/submissions/{$sub->id}/file"))->assertNotFound();
        // draft work is invisible to students
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/assignments'), [
            'kind' => 'assignment', 'title' => 'Secret draft', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id,
            'due_at' => '2026-03-20 17:00', 'submission_mode' => 'text',
        ])->assertSessionHasNoErrors();
        $draft = $this->inBranch($w['branch'], fn () => Assignment::query()->where('title', 'Secret draft')->sole());
        $this->assertSame('draft', $draft->status);
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/assignments/{$draft->id}"))->assertNotFound();
        // another branch has no access to the record at all
        $branch2 = $this->makeBranch(null);
        $admin2 = $this->branchUser($branch2, 'branch-admin');
        $this->actingOnBranch($admin2, $branch2)->get($this->branchUrl($branch2, "learning/assignments/{$hw->id}"))->assertNotFound();
    }

    public function test_due_dates_late_work_closing_and_return_for_revision(): void
    {
        $w = $this->world();
        $hw = $this->give($w, ['title' => 'Late test', 'due_at' => '2026-03-15 12:00']);
        $url = $this->branchUrl($w['branch'], "student/assignments/{$hw->id}/submit");

        $this->travelTo(now()->setDate(2026, 3, 16)->setTime(9, 0));
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($url, ['text' => 'late'])->assertSessionHasErrors('text');   // late work refused

        $this->actingOnBranch($w['teacher'], $w['branch'])->put($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}"), [
            'title' => 'Late test', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id, 'due_at' => '2026-03-15 12:00',
            'max_marks' => 10, 'submission_mode' => 'either', 'allow_late' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($url, ['text' => 'late but here'])->assertSessionHasNoErrors();
        $sub = $this->inBranch($w['branch'], fn () => AssignmentSubmission::query()->sole());
        $this->assertTrue($sub->is_late);

        // return for revision → student may hand in again, the late flag stays honest
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/evaluate"), [
            'rows' => [$w['a']->id => ['feedback' => 'Show your working', 'action' => 'return']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('returned', $sub->fresh()->status);
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($url, ['text' => 'with working'])->assertSessionHasNoErrors();
        $this->assertSame(2, $sub->fresh()->attempts);
        $this->assertSame('submitted', $sub->fresh()->status);

        // closing stops hand-ins; reopening restores them
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/close"))->assertSessionHasNoErrors();
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($url, ['text' => 'more'])->assertSessionHasErrors('text');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$hw->id}/reopen"))->assertSessionHasNoErrors();
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($url, ['text' => 'more'])->assertSessionHasNoErrors();

        // the due date of a new publication cannot be in the past unless late work is allowed
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/assignments'), [
            'kind' => 'homework', 'title' => 'Past', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id,
            'due_at' => '2026-03-01 10:00', 'submission_mode' => 'text',
        ])->assertSessionHasErrors('due_at');
    }

    public function test_submission_modes_are_enforced(): void
    {
        $w = $this->world();
        $fileOnly = $this->give($w, ['title' => 'File only', 'submission_mode' => 'file']);
        $paper = $this->give($w, ['title' => 'On paper', 'submission_mode' => 'none']);

        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$fileOnly->id}/submit"), ['text' => 'words only'])->assertSessionHasErrors('text');
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$fileOnly->id}/submit"), ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$fileOnly->id}/submit"), ['file' => UploadedFile::fake()->create('ok.pdf', 10, 'application/pdf')])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$paper->id}/submit"), ['text' => 'x'])->assertSessionHasErrors('text');

        // paper work is marked by the teacher without a hand-in
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$paper->id}/evaluate"), [
            'rows' => [$w['a']->id => ['marks' => 7, 'feedback' => 'Good notebook', 'action' => 'evaluate']],
        ])->assertSessionHasNoErrors();
        $s = $this->inBranch($w['branch'], fn () => AssignmentSubmission::query()->where('assignment_id', $paper->id)->sole());
        $this->assertNull($s->submitted_at);
        $this->assertSame('evaluated', $s->status);
        // a student of another section cannot be marked through a forged id
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$paper->id}/evaluate"), [
            'rows' => [$w['c']->id => ['marks' => 7, 'action' => 'evaluate']],
        ]);
        $this->assertSame(1, $this->inBranch($w['branch'], fn () => AssignmentSubmission::query()->where('assignment_id', $paper->id)->count()));
    }

    public function test_materials_are_private_to_the_class_and_hidden_until_shared(): void
    {
        $w = $this->world();
        $url = $this->branchUrl($w['branch'], 'teacher/learning/materials');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, [
            'type' => 'document', 'title' => 'Algebra notes', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id,
            'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'), 'is_published' => 0,
        ])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, [
            'type' => 'video', 'title' => 'Fractions video', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id,
            'url' => 'https://www.youtube.com/watch?v=abc', 'is_published' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, ['type' => 'link', 'title' => 'Bad', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id, 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, ['type' => 'document', 'title' => 'No file', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id])->assertSessionHasErrors('file');

        $doc = $this->inBranch($w['branch'], fn () => LearningMaterial::query()->where('title', 'Algebra notes')->sole());
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/materials'))->assertOk()->assertSee('Fractions video')->assertDontSee('Algebra notes');
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/materials/{$doc->id}/file"))->assertNotFound();   // hidden

        $this->actingOnBranch($w['teacher'], $w['branch'])->put($this->branchUrl($w['branch'], "teacher/learning/materials/{$doc->id}"), [
            'title' => 'Algebra notes', 'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id, 'is_published' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], "student/materials/{$doc->id}/file"))->assertOk();
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/materials/{$doc->id}/file"))->assertOk();
        $this->actingOnBranch($w['studentC'], $w['branch'])->get($this->branchUrl($w['branch'], "student/materials/{$doc->id}/file"))->assertNotFound();
        $this->actingOnBranch($w['parentC'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/materials/{$doc->id}/file"))->assertNotFound();
        $this->actingOnBranch($w['other'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/materials/{$doc->id}/file"))->assertNotFound();
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/materials/{$doc->id}/file"))->assertOk();

        $this->actingOnBranch($w['teacher'], $w['branch'])->delete($this->branchUrl($w['branch'], "teacher/learning/materials/{$doc->id}"))->assertRedirect();
        Storage::disk('local')->assertMissing($doc->file_path);
    }

    public function test_progress_report_counts_hand_ins_and_averages(): void
    {
        $w = $this->world();
        $one = $this->give($w, ['title' => 'One']);
        $two = $this->give($w, ['title' => 'Two']);
        $this->give($w, ['title' => 'Three']);
        foreach ([$one, $two] as $a) {
            $this->actingOnBranch($w['studentA'], $w['branch'])->post($this->branchUrl($w['branch'], "student/assignments/{$a->id}/submit"), ['text' => 'done']);
        }
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$one->id}/evaluate"), ['rows' => [$w['a']->id => ['marks' => 10, 'action' => 'evaluate']]]);
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/assignments/{$two->id}/evaluate"), ['rows' => [$w['a']->id => ['marks' => 5, 'action' => 'evaluate']]]);

        $p = $this->inBranch($w['branch'], fn () => app(\App\Services\Learning\LearningProgress::class)->forStudent(Student::query()->findOrFail($w['a']->id)));
        $this->assertSame(3, $p['total']);
        $this->assertSame(2, $p['done']);
        $this->assertSame(1, $p['pending']);
        $this->assertSame(0, $p['missing']);
        $this->assertSame(75.0, $p['average']);
        $this->assertSame('Mathematics', $p['subjects'][0]['name']);

        $rows = $this->inBranch($w['branch'], fn () => app(\App\Services\Learning\LearningProgress::class)->forSection($w['setup']['sectionA']->id));
        $this->assertSame(2, $rows->first()['done']);

        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], 'teacher/learning/progress'))->assertOk()->assertSee('Anika')->assertSee('75');
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/progress/students/{$w['a']->id}"))->assertOk()->assertSee('Mathematics');
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/progress/students/{$w['c']->id}"))->assertNotFound();   // not her section
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], "learning/progress/students/{$w['c']->id}"))->assertOk();

        // after the deadline the unsubmitted work counts as missing
        $this->travelTo(now()->setDate(2026, 3, 19)->setTime(9, 0));
        $p = $this->inBranch($w['branch'], fn () => app(\App\Services\Learning\LearningProgress::class)->forStudent(Student::query()->findOrFail($w['a']->id)));
        $this->assertSame(1, $p['missing']);
        $this->assertSame(0, $p['pending']);
    }
}
