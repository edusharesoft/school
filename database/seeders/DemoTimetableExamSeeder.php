<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\Period;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Branch;
use App\Models\Exams\Examination;
use App\Models\Exams\GradingScale;
use App\Models\Institution;
use App\Services\Academic\TimetableService;
use App\Services\Attendance\AttendanceSettings;
use App\Services\Exams\ExaminationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo bell schedule, class routines and one scheduled exam for every demo branch (local/staging only).
 * Idempotent: a branch that already has periods is skipped.
 */
class DemoTimetableExamSeeder extends Seeder
{
    /** [name, start, end, is_break] — day shift */
    protected array $periods = [
        ['1st period', '08:00', '08:45', false],
        ['2nd period', '08:45', '09:30', false],
        ['3rd period', '09:30', '10:15', false],
        ['Tiffin break', '10:15', '10:45', true],
        ['4th period', '10:45', '11:30', false],
        ['5th period', '11:30', '12:15', false],
        ['6th period', '12:15', '13:00', false],
    ];

    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SSC')->first();
        if (! $institution) {
            $this->command?->warn('Demo institution not found — run DemoInstitutionSeeder first.');

            return;
        }

        foreach (Branch::query()->where('institution_id', $institution->id)->with('institution')->ordered()->get() as $branch) {
            tenant()->runForBranch($branch, function () {
                if (Period::query()->exists()) {
                    return;
                }
                $session = AcademicSession::query()->current()->first();
                if (! $session) {
                    return;
                }

                foreach ($this->periods as $i => [$name, $start, $end, $break]) {
                    foreach (['day'] as $shift) {
                        Period::query()->create(['name' => $name, 'shift' => $shift, 'starts_at' => $start, 'ends_at' => $end, 'is_break' => $break, 'sort_order' => $i + 1]);
                    }
                }

                $this->seedRoutines($session);
                $this->seedExam($session);
            });
        }

        $this->command?->info('Demo periods, routines and exams seeded.');
    }

    protected function seedRoutines(AcademicSession $session): void
    {
        $service = app(TimetableService::class);
        $teachers = $service->teachers()->keys()->values();
        $days = $service->days();

        $sections = Section::query()->with('schoolClass:id,numeric_order')->where('is_active', true)->get()
            ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->values()->take(6);

        foreach ($sections as $k => $section) {
            $periods = Period::query()->where('shift', $section->shift ?: 'day')->where('is_break', false)->orderBy('sort_order')->get();
            if ($periods->isEmpty()) {
                $periods = Period::query()->where('shift', 'day')->where('is_break', false)->orderBy('sort_order')->get();
            }
            $subjects = ClassSubject::query()->where('school_class_id', $section->school_class_id)->whereNull('study_group_id')
                ->orderBy('sort_order')->pluck('subject_id')->values();
            if ($subjects->isEmpty()) {
                continue;
            }

            $cells = [];
            foreach ($days as $d => $day) {
                foreach ($periods as $p => $period) {
                    // For a fixed day & period, section k gets teacher (k + p + d) mod T: never the same teacher twice.
                    $teacher = $k < $teachers->count() ? $teachers[($k + $p + $d) % $teachers->count()] : null;
                    $cells[$day][$period->id] = [
                        'subject_id' => $subjects[($p + $d) % $subjects->count()],
                        'teacher_id' => $teacher,
                        'room_id' => $section->room_id,
                    ];
                }
            }
            $service->save($section, $session, $cells);
        }
    }

    protected function seedExam(AcademicSession $session): void
    {
        $start = CarbonImmutable::now()->addDays(14)->startOfDay();
        $this->createExam($session, 'Half-yearly examination '.$start->year, 'half_yearly', $start);
    }

    /** A scheduled exam with its papers for the first $classCount classes that have subjects (one paper a day). */
    public function createExam(AcademicSession $session, string $name, string $type, CarbonImmutable $start, int $classCount = 3): Examination
    {
        $exam = Examination::query()->create([
            'academic_session_id' => $session->id,
            'grading_scale_id' => GradingScale::defaultForBranch()->id,
            'name' => $name,
            'type' => $type,
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->addDays(30)->toDateString(),
            'min_attendance' => 70,
            'status' => 'draft',
            'instructions' => "Bring your admit card to every paper.\nReport to the exam hall 30 minutes before the paper starts.",
        ]);

        $service = app(ExaminationService::class);
        $holidays = array_map('intval', (array) app(AttendanceSettings::class)->get('weekly_holidays'));
        $classes = SchoolClass::query()->active()->ordered()->get()->filter(fn ($c) => ClassSubject::query()->where('school_class_id', $c->id)->exists())->take($classCount);
        foreach ($classes as $class) {
            $service->addClass($exam, $class);

            // one paper a day (skipping weekly holidays), 10:00–13:00
            $date = $start;
            $rows = [];
            foreach ($exam->subjects()->where('school_class_id', $class->id)->get() as $paper) {
                while (in_array((int) $date->isoWeekday(), $holidays, true)) {
                    $date = $date->addDay();
                }
                $rows[$paper->id] = [
                    'exam_date' => $date->toDateString(), 'starts_at' => '10:00', 'ends_at' => '13:00',
                    'pass_marks' => $paper->pass_marks, 'components' => $paper->components,
                    'is_optional' => $paper->is_optional, 'counts_in_gpa' => $paper->counts_in_gpa,
                ];
                $date = $date->addDay();
            }
            $service->updateClass($exam, $class->id, $rows);
        }

        if ($classes->isNotEmpty()) {
            $service->changeStatus($exam, 'scheduled');
        }

        return $exam;
    }
}
