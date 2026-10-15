<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\Subject;
use App\Models\Branch;
use App\Models\Exams\Examination;
use App\Models\Exams\ExaminationSubject;
use App\Models\Exams\GradingScale;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExaminationTest extends TestCase
{
    use RefreshDatabase;

    protected function examFixture(Branch $branch): array
    {
        $setup = $this->academicSetup($branch);

        return $this->inBranch($branch, function () use ($setup) {
            $bangla = Subject::query()->create(['name' => 'Bangla', 'code' => 'BAN', 'type' => 'theory', 'is_active' => true]);
            $physics = Subject::query()->create(['name' => 'Physics', 'code' => 'PHY', 'type' => 'theory_practical', 'is_active' => true]);
            $agri = Subject::query()->create(['name' => 'Agriculture', 'code' => 'AGR', 'type' => 'theory', 'is_active' => true]);
            ClassSubject::query()->create(['school_class_id' => $setup['class']->id, 'subject_id' => $bangla->id, 'category' => 'compulsory', 'full_marks' => 100, 'pass_marks' => 33, 'sort_order' => 1]);
            ClassSubject::query()->create(['school_class_id' => $setup['class']->id, 'subject_id' => $physics->id, 'category' => 'compulsory', 'full_marks' => 100, 'pass_marks' => 33, 'sort_order' => 2]);
            ClassSubject::query()->create(['school_class_id' => $setup['class']->id, 'subject_id' => $agri->id, 'category' => 'optional', 'full_marks' => 100, 'pass_marks' => 33, 'sort_order' => 3]);

            return compact('setup', 'bangla', 'physics', 'agri');
        });
    }

    protected function createExam(Branch $branch, $admin, array $f, array $overrides = []): Examination
    {
        $scale = $this->inBranch($branch, fn () => GradingScale::defaultForBranch());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'exams'), array_merge([
            'name' => 'Half-yearly 2026', 'type' => 'half_yearly', 'academic_session_id' => $f['setup']['session']->id,
            'grading_scale_id' => $scale->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-15', 'min_attendance' => 75,
        ], $overrides))->assertSessionHasNoErrors();

        return $this->inBranch($branch, fn () => Examination::query()->latest('id')->firstOrFail());
    }

    public function test_default_bangladesh_grading_scale_is_created_once_and_can_be_edited(): void
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'exams/grading'))->assertOk()->assertSee('A+')->assertSee('GPA 5.00');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'exams/grading'))->assertOk();
        $scale = $this->inBranch($branch, fn () => GradingScale::query()->sole());
        $this->assertTrue($scale->is_default);
        $this->assertSame('F', $scale->failGrade());

        // A scale without a zero-point band or starting above 0 % is refused.
        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, "exams/grading/{$scale->id}"), [
            'name' => 'Broken', 'max_gpa' => 5, 'optional_base_point' => 2, 'gpa_decimals' => 2, 'rounding' => 'round',
            'grades' => [['grade' => 'A', 'min' => 50, 'max' => 100, 'point' => 5], ['grade' => 'B', 'min' => 10, 'max' => 49.99, 'point' => 3], ['grade' => '', 'min' => '', 'max' => '', 'point' => '']],
        ])->assertSessionHasErrors('grades');

        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, "exams/grading/{$scale->id}"), [
            'name' => 'Pass / fail', 'max_gpa' => 4, 'optional_base_point' => 0, 'gpa_decimals' => 1, 'rounding' => 'floor', 'is_default' => 1,
            'grades' => [['grade' => 'P', 'min' => 40, 'max' => 100, 'point' => 4], ['grade' => '', 'min' => '', 'max' => '', 'point' => ''], ['grade' => 'X', 'min' => 0, 'max' => 39.99, 'point' => 0]],
        ])->assertSessionHasNoErrors();

        $scale->refresh();
        $this->assertSame(['P', 'X'], array_column($scale->grades, 'grade'));
        $this->assertSame('X', $scale->failGrade());
    }

    public function test_adding_a_class_creates_papers_with_default_components(): void
    {
        $branch = $this->makeBranch();
        $f = $this->examFixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $exam = $this->createExam($branch, $admin, $f);
        $this->assertSame('draft', $exam->status);

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "exams/{$exam->id}/classes"), ['school_class_id' => $f['setup']['class']->id])
            ->assertSessionHasNoErrors();
        // Adding the same class again adds nothing new.
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "exams/{$exam->id}/classes"), ['school_class_id' => $f['setup']['class']->id]);

        $papers = $this->inBranch($branch, fn () => ExaminationSubject::query()->get()->keyBy('subject_id'));
        $this->assertCount(3, $papers);
        $this->assertEquals(['theory' => ['full' => 100, 'pass' => 33]], $papers[$f['bangla']->id]->components);
        $this->assertEquals(['theory' => ['full' => 75, 'pass' => 25], 'practical' => ['full' => 25, 'pass' => 9]], $papers[$f['physics']->id]->components);
        $this->assertTrue($papers[$f['agri']->id]->is_optional);

        // A class without subjects is refused.
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "exams/{$exam->id}/classes"), ['school_class_id' => $f['setup']['next']->id])
            ->assertSessionHasErrors('school_class_id');

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "exams/{$exam->id}"))->assertOk()->assertSee('Physics')->assertSee('Six');
    }

    public function test_papers_schedule_is_validated_for_marks_period_and_clashes(): void
    {
        $branch = $this->makeBranch();
        $f = $this->examFixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $exam = $this->createExam($branch, $admin, $f);
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "exams/{$exam->id}/classes"), ['school_class_id' => $f['setup']['class']->id]);
        $papers = $this->inBranch($branch, fn () => ExaminationSubject::query()->get()->keyBy('subject_id'));
        $b = $papers[$f['bangla']->id];
        $p = $papers[$f['physics']->id];
        $url = $this->branchUrl($branch, "exams/{$exam->id}/papers");
        $row = fn ($date, $start, $end, $theory = [100, 33], $pass = 33) => [
            'exam_date' => $date, 'starts_at' => $start, 'ends_at' => $end, 'pass_marks' => $pass, 'counts_in_gpa' => 1,
            'components' => ['theory' => ['full' => $theory[0], 'pass' => $theory[1]]],
        ];

        // Component pass above full, date outside the exam period, overlapping papers.
        $this->actingOnBranch($admin, $branch)->put($url, ['school_class_id' => $f['setup']['class']->id, 'rows' => [
            $b->id => $row('2026-06-02', '10:00', '13:00', [100, 120]),
        ]])->assertSessionHasErrors("rows.{$b->id}.components.theory.pass");
        $this->actingOnBranch($admin, $branch)->put($url, ['school_class_id' => $f['setup']['class']->id, 'rows' => [
            $b->id => $row('2026-07-02', '10:00', '13:00'),
        ]])->assertSessionHasErrors("rows.{$b->id}.exam_date");
        $this->actingOnBranch($admin, $branch)->put($url, ['school_class_id' => $f['setup']['class']->id, 'rows' => [
            $b->id => $row('2026-06-02', '10:00', '13:00'),
            $p->id => $row('2026-06-02', '12:00', '14:00'),
        ]])->assertSessionHasErrors("rows.{$p->id}.starts_at");

        // Valid schedule: CA component added, practical dropped, total recomputed.
        $this->actingOnBranch($admin, $branch)->put($url, ['school_class_id' => $f['setup']['class']->id, 'rows' => [
            $b->id => $row('2026-06-02', '10:00', '13:00'),
            $p->id => ['exam_date' => '2026-06-03', 'starts_at' => '10:00', 'ends_at' => '13:00', 'pass_marks' => 40, 'counts_in_gpa' => 1,
                'components' => ['theory' => ['full' => 80, 'pass' => 26], 'practical' => ['full' => '', 'pass' => ''], 'ca' => ['full' => 20, 'pass' => 0]]],
        ]])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(100.0, $p->full_marks);
        $this->assertSame(['theory', 'ca'], array_keys($p->activeComponents()));
        $this->assertSame('2026-06-03', $p->exam_date->toDateString());

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "exams/{$exam->id}/routine"))->assertOk()
            ->assertSee('Bangla')->assertSee('03 Jun 2026');
    }

    public function test_status_transitions_and_locking(): void
    {
        $branch = $this->makeBranch();
        $f = $this->examFixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $exam = $this->createExam($branch, $admin, $f);
        $status = fn (string $to) => $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "exams/{$exam->id}/status"), ['status' => $to]);

        $status('scheduled')->assertSessionHasErrors('status'); // no papers yet
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "exams/{$exam->id}/classes"), ['school_class_id' => $f['setup']['class']->id]);
        $status('scheduled')->assertSessionHasNoErrors();
        $status('published')->assertSessionHasErrors('status'); // results workflow only
        $status('marks_entry')->assertSessionHasNoErrors();
        $this->assertSame('marks_entry', $this->inBranch($branch, fn () => $exam->fresh()->status));

        // Papers cannot be removed once marks entry started; a non-draft exam cannot be deleted.
        $paper = $this->inBranch($branch, fn () => ExaminationSubject::query()->first());
        $this->actingOnBranch($admin, $branch)->delete($this->branchUrl($branch, "exams/{$exam->id}/papers/{$paper->id}"))->assertStatus(422);
        $this->actingOnBranch($admin, $branch)->delete($this->branchUrl($branch, "exams/{$exam->id}"))->assertStatus(422);

        // An approved exam is locked for edits.
        $this->inBranch($branch, fn () => $exam->forceFill(['status' => 'approved'])->save());
        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, "exams/{$exam->id}"), [
            'name' => 'Changed', 'type' => 'annual', 'academic_session_id' => $f['setup']['session']->id, 'grading_scale_id' => $exam->grading_scale_id,
        ])->assertStatus(422);
    }

    public function test_exams_are_isolated_per_branch_and_teachers_cannot_manage_them(): void
    {
        $institution = Institution::factory()->create();
        $branch = $this->makeBranch($institution);
        $other = $this->makeBranch($institution);
        $f = $this->examFixture($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $otherAdmin = $this->branchUser($other, 'branch-admin');
        $exam = $this->createExam($branch, $admin, $f);

        $this->actingOnBranch($otherAdmin, $other)->get($this->branchUrl($other, "exams/{$exam->id}"))->assertNotFound();
        $this->actingOnBranch($otherAdmin, $other)->get($this->branchUrl($other, 'exams'))->assertOk()->assertDontSee('Half-yearly 2026');

        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->actingOnBranch($teacher, $branch)->post($this->branchUrl($branch, 'exams'), ['name' => 'X'])->assertForbidden();
    }
}
