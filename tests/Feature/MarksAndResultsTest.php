<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\Period;
use App\Models\Academic\Subject;
use App\Models\Academic\TimetableEntry;
use App\Models\Attendance\StudentAttendance;
use App\Models\Branch;
use App\Models\Exams\ExamMark;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Models\Exams\ExaminationSubject;
use App\Models\Exams\GradingScale;
use App\Models\Exams\MarkCorrection;
use App\Models\User;
use App\Services\Exams\ExaminationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarksAndResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(10, 0));
    }

    /**
     * Class Six with Bangla (theory 100), Physics (75 theory / 25 practical) and Agriculture (optional 4th subject);
     * students A, B, C in section A and D in section B; a scheduled exam with the three papers.
     */
    protected function fixture(Branch $branch): array
    {
        $setup = $this->academicSetup($branch);
        $students = [
            'a' => $this->enrolStudent($branch, $setup, 'Anika'),
            'b' => $this->enrolStudent($branch, $setup, 'Babul'),
            'c' => $this->enrolStudent($branch, $setup, 'Chandni'),
            'd' => $this->enrolStudent($branch, $setup, 'Dipu', $setup['sectionB']),
        ];

        $exam = $this->inBranch($branch, function () use ($setup) {
            $subjects = [];
            foreach ([['Bangla', 'BAN', 'theory', 'compulsory'], ['Physics', 'PHY', 'theory_practical', 'compulsory'], ['Agriculture', 'AGR', 'theory', 'optional']] as $i => [$name, $code, $type, $cat]) {
                $subjects[$code] = Subject::query()->create(['name' => $name, 'code' => $code, 'type' => $type, 'is_active' => true]);
                ClassSubject::query()->create(['school_class_id' => $setup['class']->id, 'subject_id' => $subjects[$code]->id, 'category' => $cat, 'full_marks' => 100, 'pass_marks' => 33, 'sort_order' => $i]);
            }
            $exam = Examination::query()->create([
                'academic_session_id' => $setup['session']->id, 'grading_scale_id' => GradingScale::defaultForBranch()->id,
                'name' => 'Half-yearly 2026', 'type' => 'half_yearly', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-15', 'min_attendance' => 75, 'status' => 'draft',
            ]);
            app(ExaminationService::class)->addClass($exam, $setup['class']);
            app(ExaminationService::class)->changeStatus($exam, 'scheduled');

            return $exam;
        });

        $papers = $this->inBranch($branch, fn () => ExaminationSubject::query()->with('subject')->get()->keyBy(fn ($p) => $p->subject->code));

        return compact('setup', 'students', 'exam', 'papers');
    }

    protected function sheetUrl(Branch $branch, array $f, string $code, string $section = 'sectionA', bool $portal = false): string
    {
        $ids = $f['exam']->id.'/'.($portal ? '' : 'marks/').$f['papers'][$code]->id.'/'.$f['setup'][$section]->id;

        return $this->branchUrl($branch, $portal ? 'teacher/marks/'.$ids : 'exams/'.$ids);
    }

    protected function enterAll(Branch $branch, User $admin, array $f): void
    {
        $s = $f['students'];
        $post = fn (string $code, string $section, array $entries) => $this->actingOnBranch($admin, $branch)
            ->post($this->sheetUrl($branch, $f, $code, $section), ['entries' => $entries])->assertSessionHasNoErrors();

        $post('BAN', 'sectionA', [$s['a']->id => ['theory' => 85], $s['b']->id => ['theory' => 65], $s['c']->id => ['theory' => 20]]);
        $post('PHY', 'sectionA', [$s['a']->id => ['theory' => 70, 'practical' => 20], $s['b']->id => ['theory' => 50, 'practical' => 20], $s['c']->id => ['theory' => 60, 'practical' => 15]]);
        $post('AGR', 'sectionA', [$s['a']->id => ['theory' => 75]]);
        $post('BAN', 'sectionB', [$s['d']->id => ['theory' => 80]]);
    }

    public function test_marks_are_validated_saved_and_cleared_and_the_exam_moves_to_marks_entry(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $f['students']['a'];

        $this->actingOnBranch($admin, $branch)->get($this->sheetUrl($branch, $f, 'PHY'))->assertOk()->assertSee('Anika')->assertDontSee('Dipu');
        $this->actingOnBranch($admin, $branch)->post($this->sheetUrl($branch, $f, 'PHY'), ['entries' => [$a->id => ['theory' => 80, 'practical' => 10]]])
            ->assertSessionHasErrors("entries.{$a->id}.theory"); // above 75
        $this->assertSame(0, $this->inBranch($branch, fn () => ExamMark::query()->count()));

        $this->actingOnBranch($admin, $branch)->post($this->sheetUrl($branch, $f, 'PHY'), ['entries' => [$a->id => ['theory' => 70, 'practical' => '']]])->assertSessionHasNoErrors();
        $mark = $this->inBranch($branch, fn () => ExamMark::query()->sole());
        $this->assertNull($mark->total); // practical still missing
        $this->assertSame('marks_entry', $this->inBranch($branch, fn () => $f['exam']->fresh()->status));

        $this->actingOnBranch($admin, $branch)->post($this->sheetUrl($branch, $f, 'PHY'), ['entries' => [$a->id => ['absent' => 1]]])->assertSessionHasNoErrors();
        $this->assertTrue($this->inBranch($branch, fn () => ExamMark::query()->sole()->is_absent));

        $this->actingOnBranch($admin, $branch)->post($this->sheetUrl($branch, $f, 'PHY'), ['entries' => [$a->id => ['theory' => '', 'practical' => '']]])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->inBranch($branch, fn () => ExamMark::query()->count()));

        // A student of another section is ignored.
        $this->actingOnBranch($admin, $branch)->post($this->sheetUrl($branch, $f, 'PHY'), ['entries' => [$f['students']['d']->id => ['theory' => 50, 'practical' => 20]]]);
        $this->assertSame(0, $this->inBranch($branch, fn () => ExamMark::query()->count()));
    }

    public function test_teachers_enter_marks_only_for_the_subjects_and_sections_they_teach(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->inBranch($branch, function () use ($f, $teacher) {
            $period = Period::query()->create(['name' => '1st', 'shift' => 'day', 'starts_at' => '08:00', 'ends_at' => '08:45', 'sort_order' => 1]);
            TimetableEntry::query()->create(['academic_session_id' => $f['setup']['session']->id, 'section_id' => $f['setup']['sectionA']->id,
                'period_id' => $period->id, 'day_of_week' => 6, 'subject_id' => $f['papers']['BAN']->subject_id, 'teacher_id' => $teacher->id]);
        });
        $a = $f['students']['a'];

        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'teacher/marks'))->assertOk()
            ->assertSee('teacher/marks/'.$f['exam']->id.'/'.$f['papers']['BAN']->id.'/'.$f['setup']['sectionA']->id, false)
            ->assertDontSee('teacher/marks/'.$f['exam']->id.'/'.$f['papers']['PHY']->id.'/', false);

        $this->actingOnBranch($teacher, $branch)->post($this->sheetUrl($branch, $f, 'BAN', 'sectionA', true), ['entries' => [$a->id => ['theory' => 77]]])
            ->assertSessionHasNoErrors();
        $this->actingOnBranch($teacher, $branch)->post($this->sheetUrl($branch, $f, 'PHY', 'sectionA', true), ['entries' => [$a->id => ['theory' => 50, 'practical' => 20]]])
            ->assertForbidden();
        $this->actingOnBranch($teacher, $branch)->get($this->sheetUrl($branch, $f, 'BAN', 'sectionB', true))->assertForbidden();

        $this->assertSame(1, $this->inBranch($branch, fn () => ExamMark::query()->count()));
    }

    public function test_results_are_processed_ranked_approved_and_published(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->enterAll($branch, $admin, $f);
        $examUrl = fn (string $path) => $this->branchUrl($branch, 'exams/'.$f['exam']->id.'/results'.$path);

        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->actingOnBranch($teacher, $branch)->post($examUrl('/process'))->assertForbidden();

        $this->actingOnBranch($admin, $branch)->post($examUrl('/process'))->assertSessionHasNoErrors();
        $results = $this->inBranch($branch, fn () => ExamResult::query()->get()->keyBy('student_id'));
        [$a, $b, $c, $d] = array_map(fn ($s) => $results[$s->id], array_values($f['students']));

        $this->assertSame(['pass', 5.0, 'A+', 1, 1], [$a->status, $a->gpa, $a->grade, $a->class_position, $a->section_position]);
        $this->assertSame(['pass', 3.75, 'A-', 2], [$b->status, $b->gpa, $b->grade, $b->class_position]);
        $this->assertSame(['fail', 0.0, 'F', 3, 1], [$c->status, $c->gpa, $c->grade, $c->class_position, $c->failed_subjects]);
        $this->assertSame(['incomplete', null, null], [$d->status, $d->gpa, $d->class_position]);
        $this->assertCount(3, $a->subjects);  // optional subject taken
        $this->assertCount(2, $b->subjects);  // optional subject not taken → left out
        $this->assertSame(250.0, $a->total_marks);

        // Not visible to the public before publication.
        $lookup = ['examination_id' => $f['exam']->id, 'student_code' => $f['students']['a']->student_code, 'date_of_birth' => '2014-01-01'];
        $this->post($this->branchUrl($branch, 'results'), $lookup)->assertOk()->assertSee(__('No published result matches this student ID and date of birth.'));

        $this->actingOnBranch($admin, $branch)->post($examUrl('/publish'))->assertSessionHasErrors('status'); // not approved yet
        $this->actingOnBranch($admin, $branch)->post($examUrl('/approve'))->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->post($examUrl('/publish'))->assertSessionHasNoErrors();
        $this->assertSame('published', $this->inBranch($branch, fn () => $f['exam']->fresh()->status));

        $this->post($this->branchUrl($branch, 'results'), $lookup)->assertOk()->assertSee('Anika')->assertSee('5.00')->assertDontSee('NOT PUBLISHED');
        $this->post($this->branchUrl($branch, 'results'), ['date_of_birth' => '2014-02-02'] + $lookup)->assertOk()->assertDontSee('Anika');

        // Printable documents render.
        $this->actingOnBranch($admin, $branch)->get($examUrl('?class_id='.$f['setup']['class']->id))->assertOk()->assertSee('Chandni');
        $this->actingOnBranch($admin, $branch)->get($examUrl('/tabulation?class_id='.$f['setup']['class']->id))->assertOk()->assertSee('BAN')->assertSee('Babul');
        $this->actingOnBranch($admin, $branch)->get($examUrl('/marksheets?section_id='.$f['setup']['sectionA']->id))->assertOk()->assertSee('Chandni');
        $this->actingOnBranch($admin, $branch)->get($examUrl('/'.$a->id))->assertOk()->assertSee('Anika');
    }

    public function test_mark_corrections_need_approval_and_send_the_exam_back_for_re_approval(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->inBranch($branch, function () use ($f, $teacher) {
            $period = Period::query()->create(['name' => '1st', 'shift' => 'day', 'starts_at' => '08:00', 'ends_at' => '08:45', 'sort_order' => 1]);
            TimetableEntry::query()->create(['academic_session_id' => $f['setup']['session']->id, 'section_id' => $f['setup']['sectionA']->id,
                'period_id' => $period->id, 'day_of_week' => 6, 'subject_id' => $f['papers']['BAN']->subject_id, 'teacher_id' => $teacher->id]);
        });
        $this->enterAll($branch, $admin, $f);
        $examUrl = fn (string $path) => $this->branchUrl($branch, 'exams/'.$f['exam']->id.'/results'.$path);
        $this->actingOnBranch($admin, $branch)->post($examUrl('/process'));
        $this->actingOnBranch($admin, $branch)->post($examUrl('/approve'));
        $c = $f['students']['c'];

        // Marks are locked now.
        $this->actingOnBranch($admin, $branch)->post($this->sheetUrl($branch, $f, 'BAN'), ['entries' => [$c->id => ['theory' => 40]]])->assertSessionHasErrors('entries');

        $request = ['examination_subject_id' => $f['papers']['BAN']->id, 'student_id' => $c->id, 'marks' => ['theory' => 40], 'reason' => 'Addition error on the script'];
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'teacher/marks/corrections'), $request)->assertSessionHasNoErrors();
        $correction = $this->inBranch($branch, fn () => MarkCorrection::query()->sole());
        $this->assertSame('pending', $correction->status);
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'teacher/marks/corrections'), $request)->assertSessionHasErrors('reason'); // already pending

        // Teachers cannot review; the exam controller approves.
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, "exams/corrections/{$correction->id}/approve"))->assertForbidden();
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "exams/corrections/{$correction->id}/approve"))->assertSessionHasNoErrors();

        $this->assertSame(40.0, $this->inBranch($branch, fn () => ExamMark::query()->where('student_id', $c->id)->where('examination_subject_id', $f['papers']['BAN']->id)->sole()->total));
        $this->assertSame('processing', $this->inBranch($branch, fn () => $f['exam']->fresh()->status)); // needs approval again
        $this->assertSame('pass', $this->inBranch($branch, fn () => ExamResult::query()->where('student_id', $c->id)->sole()->status));
    }

    public function test_student_and_parent_portals_show_only_their_published_results_and_admit_cards_check_attendance(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $f['students']['a'];
        $b = $f['students']['b'];

        // Portal logins from the student profile.
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$a->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $guardian = $this->inBranch($branch, fn () => $a->guardians()->first());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$a->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $studentUser = User::query()->where('username', strtolower($a->student_code))->sole();
        $parentUser = User::query()->where('phone', $guardian->phone)->sole();
        $this->assertSame('student', $studentUser->portal());
        $this->assertSame('parent', $parentUser->portal());

        // Admit card: attendance 50 % < 75 % → refused; with good attendance → shown.
        $this->inBranch($branch, function () use ($a, $f) {
            foreach (['2026-05-02' => 'present', '2026-05-03' => 'absent'] as $date => $status) {
                StudentAttendance::query()->create(['student_id' => $a->id, 'student_enrollment_id' => $a->enrollments()->value('id'), 'section_id' => $f['setup']['sectionA']->id, 'school_class_id' => $f['setup']['class']->id,
                    'academic_session_id' => $f['setup']['session']->id, 'date' => $date, 'slot' => 'day', 'status' => $status]);
            }
        });
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, "student/exams/{$f['exam']->id}/admit-card"))->assertForbidden();
        $this->inBranch($branch, fn () => StudentAttendance::query()->where('status', 'absent')->update(['status' => 'present']));
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, "student/exams/{$f['exam']->id}/admit-card"))->assertOk()->assertSee('Anika')->assertSee('Physics');

        // Results appear after publication only.
        $this->enterAll($branch, $admin, $f);
        $examUrl = fn (string $path) => $this->branchUrl($branch, 'exams/'.$f['exam']->id.'/results'.$path);
        $this->actingOnBranch($admin, $branch)->post($examUrl('/process'));
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, 'student/results'))->assertOk()->assertDontSee('Half-yearly 2026');
        $this->actingOnBranch($admin, $branch)->post($examUrl('/approve'));
        $this->actingOnBranch($admin, $branch)->post($examUrl('/publish'));

        [$ra, $rb] = $this->inBranch($branch, fn () => [ExamResult::query()->where('student_id', $a->id)->sole(), ExamResult::query()->where('student_id', $b->id)->sole()]);
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, 'student'))->assertOk()->assertSee('Half-yearly 2026')->assertSee('5.00');
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, "student/results/{$ra->id}"))->assertOk()->assertSee('Anika');
        $this->actingOnBranch($studentUser, $branch)->get($this->branchUrl($branch, "student/results/{$rb->id}"))->assertNotFound();

        $this->actingOnBranch($parentUser, $branch)->get($this->branchUrl($branch, 'parent'))->assertOk()->assertSee('Anika')->assertDontSee('Babul');
        $this->actingOnBranch($parentUser, $branch)->get($this->branchUrl($branch, "parent/children/{$a->id}/results"))->assertOk()->assertSee('Half-yearly 2026');
        $this->actingOnBranch($parentUser, $branch)->get($this->branchUrl($branch, "parent/children/{$b->id}/results"))->assertNotFound();
        $this->actingOnBranch($parentUser, $branch)->get($this->branchUrl($branch, "parent/results/{$rb->id}"))->assertNotFound();

        // Portal users never reach the back-office.
        $this->actingOnBranch($studentUser, $branch)->get($examUrl(''))->assertForbidden();
    }

    public function test_admit_cards_and_seat_plan_render_for_the_office(): void
    {
        $branch = $this->makeBranch();
        $f = $this->fixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->inBranch($branch, fn () => \App\Models\Academic\Room::query()->create(['name' => 'Hall 1', 'code' => 'H1', 'type' => 'hall', 'capacity' => 2, 'is_active' => true]));

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "exams/{$f['exam']->id}/admit-cards?section_id={$f['setup']['sectionA']->id}"))
            ->assertOk()->assertSee('Anika')->assertSee('Chandni');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "exams/{$f['exam']->id}/seat-plan"))
            ->assertOk()->assertSee('Hall 1')->assertSee(__(':count candidate(s) could not be seated — select more rooms.', ['count' => 2]));
    }
}
