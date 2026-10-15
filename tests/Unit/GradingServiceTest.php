<?php

namespace Tests\Unit;

use App\Models\Exams\GradingScale;
use App\Services\Exams\GradingService;
use PHPUnit\Framework\TestCase;

class GradingServiceTest extends TestCase
{
    protected GradingService $g;

    protected array $grades;

    protected array $theoryPractical = ['theory' => ['full' => 50, 'pass' => 17], 'practical' => ['full' => 25, 'pass' => 8], 'ca' => ['full' => 25, 'pass' => 0]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->g = new GradingService;
        $this->grades = GradingScale::BANGLADESH_DEFAULT;
    }

    public function test_percentage_bands_including_boundaries(): void
    {
        $this->assertSame(['grade' => 'A+', 'point' => 5.0], $this->g->grade(80, $this->grades));
        $this->assertSame(['grade' => 'A', 'point' => 4.0], $this->g->grade(79.99, $this->grades));
        $this->assertSame(['grade' => 'A-', 'point' => 3.5], $this->g->grade(60, $this->grades));
        $this->assertSame(['grade' => 'D', 'point' => 1.0], $this->g->grade(33, $this->grades));
        $this->assertSame(['grade' => 'F', 'point' => 0.0], $this->g->grade(32.99, $this->grades));
        $this->assertSame(['grade' => 'F', 'point' => 0.0], $this->g->grade(0, $this->grades));
    }

    public function test_subject_pass_with_components(): void
    {
        $r = $this->g->subject($this->theoryPractical, 33, ['theory' => 40, 'practical' => 20, 'ca' => 20], false, $this->grades);

        $this->assertSame('pass', $r['status']);
        $this->assertSame(80.0, $r['total']);
        $this->assertSame(80.0, $r['percent']);
        $this->assertSame(['A+', 5.0], [$r['grade'], $r['point']]);
    }

    public function test_failing_one_component_fails_the_subject_even_with_high_total(): void
    {
        $r = $this->g->subject($this->theoryPractical, 33, ['theory' => 15, 'practical' => 25, 'ca' => 25], false, $this->grades);

        $this->assertSame('fail', $r['status']);
        $this->assertSame(65.0, $r['total']);
        $this->assertSame(['F', 0.0], [$r['grade'], $r['point']]);
        $this->assertSame(['theory'], $r['failed_components']);
    }

    public function test_total_below_the_overall_pass_mark_fails(): void
    {
        $components = ['theory' => ['full' => 100, 'pass' => 0]];
        $this->assertSame('fail', $this->g->subject($components, 33, ['theory' => 32.5], false, $this->grades)['status']);
        $this->assertSame('pass', $this->g->subject($components, 33, ['theory' => 33], false, $this->grades)['status']);
    }

    public function test_absent_and_incomplete_marks(): void
    {
        $absent = $this->g->subject($this->theoryPractical, 33, [], true, $this->grades);
        $this->assertSame(['absent', 'F', 0.0], [$absent['status'], $absent['grade'], $absent['point']]);

        $incomplete = $this->g->subject($this->theoryPractical, 33, ['theory' => 40, 'practical' => null, 'ca' => 20], false, $this->grades);
        $this->assertSame('incomplete', $incomplete['status']);
        $this->assertNull($incomplete['grade']);
    }

    public function test_gpa_average_of_compulsory_subjects_with_rounding(): void
    {
        $subjects = [
            ['status' => 'pass', 'point' => 5.0], ['status' => 'pass', 'point' => 4.0], ['status' => 'pass', 'point' => 3.5],
        ];
        $r = $this->g->gpa($subjects, ['grades' => $this->grades]);

        $this->assertSame('pass', $r['status']);
        $this->assertSame(4.17, $r['gpa']);            // 12.5 / 3 = 4.1666…
        $this->assertSame('A', $r['grade']);

        $floor = $this->g->gpa($subjects, ['grades' => $this->grades, 'rounding' => 'floor']);
        $this->assertSame(4.16, $floor['gpa']);
    }

    public function test_optional_subject_adds_only_points_above_the_base_and_gpa_is_capped(): void
    {
        $subjects = [
            ['status' => 'pass', 'point' => 4.0], ['status' => 'pass', 'point' => 4.0],
            ['status' => 'pass', 'point' => 5.0, 'is_optional' => true],
        ];
        $r = $this->g->gpa($subjects, ['grades' => $this->grades]);
        $this->assertSame(5.0, $r['gpa']);             // (8 + 3) / 2 = 5.5 → capped at 5.00
        $this->assertSame(4.0, $r['gpa_without_optional']);
        $this->assertSame('A+', $r['grade']);

        $low = $this->g->gpa([['status' => 'pass', 'point' => 3.0], ['status' => 'pass', 'point' => 1.0, 'is_optional' => true]], ['grades' => $this->grades]);
        $this->assertSame(3.0, $low['gpa']);           // optional below base adds nothing

        $failedOptional = $this->g->gpa([['status' => 'pass', 'point' => 3.5], ['status' => 'fail', 'point' => 0.0, 'is_optional' => true]], ['grades' => $this->grades]);
        $this->assertSame(['pass', 3.5], [$failedOptional['status'], $failedOptional['gpa']]); // failing the 4th subject does not fail the result
    }

    public function test_any_failed_or_absent_compulsory_subject_fails_the_result(): void
    {
        $r = $this->g->gpa([
            ['status' => 'pass', 'point' => 5.0], ['status' => 'absent', 'point' => 0.0], ['status' => 'fail', 'point' => 0.0],
        ], ['grades' => $this->grades]);

        $this->assertSame(['fail', 0.0, 'F', 2], [$r['status'], $r['gpa'], $r['grade'], $r['failed_subjects']]);
    }

    public function test_incomplete_subject_makes_the_result_incomplete_and_non_gpa_subjects_are_ignored(): void
    {
        $r = $this->g->gpa([['status' => 'pass', 'point' => 5.0], ['status' => 'incomplete', 'point' => null]], ['grades' => $this->grades]);
        $this->assertSame('incomplete', $r['status']);
        $this->assertNull($r['gpa']);

        $ignored = $this->g->gpa([['status' => 'pass', 'point' => 4.0], ['status' => 'fail', 'point' => 0.0, 'counts_in_gpa' => false]], ['grades' => $this->grades]);
        $this->assertSame(['pass', 4.0], [$ignored['status'], $ignored['gpa']]);
    }

    public function test_letter_grade_from_gpa(): void
    {
        $this->assertSame('A+', $this->g->letterForGpa(5.0, $this->grades));
        $this->assertSame('A', $this->g->letterForGpa(4.99, $this->grades));
        $this->assertSame('A-', $this->g->letterForGpa(3.5, $this->grades));
        $this->assertSame('D', $this->g->letterForGpa(1.2, $this->grades));
        $this->assertSame('F', $this->g->letterForGpa(0.0, $this->grades));
    }
}
