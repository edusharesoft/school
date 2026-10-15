<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\Section;
use App\Models\Branch;
use App\Models\Exams\ExamMark;
use App\Models\Exams\Examination;
use App\Models\Institution;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Exams\MarkEntryService;
use App\Services\Exams\ResultService;
use App\Services\Students\PortalAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demo marks & published results for every demo branch (local/staging only): a past "First term examination"
 * with random but repeatable marks, processed, approved and published; plus portal logins for the first
 * student of Six-A and that student's primary guardian (password "password").
 * Idempotent: a branch that already has an exam named "First term examination …" is skipped.
 */
class DemoResultsSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SSC')->first();
        if (! $institution) {
            $this->command?->warn('Demo institution not found — run DemoInstitutionSeeder first.');

            return;
        }

        foreach (Branch::query()->where('institution_id', $institution->id)->with('institution')->ordered()->get() as $branch) {
            tenant()->runForBranch($branch, function () use ($branch) {
                $session = AcademicSession::query()->current()->first();
                if (! $session || Examination::query()->where('name', 'like', 'First term examination%')->exists()) {
                    return;
                }
                $admin = User::query()->where('email', "admin.branch{$branch->sort_order}@demo.test")->first()
                    ?? User::query()->withAccessToBranch($branch)->first();
                if (! $admin) {
                    return;
                }

                mt_srand($branch->id * 104729);
                $start = CarbonImmutable::today()->subDays(40);
                $exam = (new DemoTimetableExamSeeder)->createExam($session, 'First term examination '.$start->year, 'term', $start);
                if ($exam->status !== 'scheduled') {
                    return;
                }

                $this->enterMarks($exam, $admin);
                $results = app(ResultService::class);
                $results->process($exam->fresh(), $admin);
                $results->approve($exam->fresh(), $admin);
                $results->publish($exam->fresh(), $admin);

                $this->portalLogins($admin);
            });
        }

        $this->command?->info('Demo marks, published results and portal logins seeded (student / guardian password: password).');
    }

    protected function enterMarks(Examination $exam, User $admin): void
    {
        $marks = app(MarkEntryService::class);
        foreach ($exam->subjects()->get() as $paper) {
            $paper->setRelation('examination', $exam->fresh());
            foreach (Section::query()->where('school_class_id', $paper->school_class_id)->get() as $section) {
                $entries = [];
                foreach ($marks->candidates($paper, $section) as $enrollment) {
                    $ability = 0.35 + (($enrollment->student_id * 37) % 60) / 100; // steady per student: 0.35 … 0.94
                    if (mt_rand(1, 100) <= 3) {
                        $entries[$enrollment->student_id] = ['absent' => 1];

                        continue;
                    }
                    foreach ($paper->activeComponents() as $name => $c) {
                        $score = $c['full'] * min(1, max(0, $ability + mt_rand(-15, 15) / 100));
                        $entries[$enrollment->student_id][$name] = (int) round($score);
                    }
                }
                if ($entries) {
                    $marks->save($paper, $section, $entries, $admin);
                }
            }
        }
    }

    protected function portalLogins(User $admin): void
    {
        $student = Student::query()->whereNull('user_id')->where('status', 'active')->orderBy('id')->first();
        if (! $student) {
            return;
        }
        $accounts = app(PortalAccountService::class);
        if (! User::query()->where('username', strtolower($student->student_code))->exists()) {
            $accounts->forStudent($student, 'password', null, $admin);
        }
        $guardian = $student->guardians()->whereNull('guardians.user_id')->first();
        if ($guardian && $guardian->phone && ! User::query()->where('phone', $guardian->phone)->exists()) {
            $accounts->forGuardian($guardian, 'password', null, $admin);
        }
    }
}
