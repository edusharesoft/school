<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\ClassSubject;
use App\Models\Academic\Room;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Academic\StudyGroup;
use App\Models\Academic\Subject;
use App\Models\Branch;
use App\Models\Institution;
use App\Services\Students\StudentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo academic structure + students for every demo branch (local/staging only).
 * Idempotent: a branch that already has a "2026" session is skipped.
 */
class DemoAcademicSeeder extends Seeder
{
    /** code => [name, name_bn, level, order, has_groups, sections] */
    protected array $classes = [
        'VI' => ['Six', 'ষষ্ঠ', 'junior_secondary', 6, false, ['A', 'B']],
        'VII' => ['Seven', 'সপ্তম', 'junior_secondary', 7, false, ['A', 'B']],
        'VIII' => ['Eight', 'অষ্টম', 'junior_secondary', 8, false, ['A', 'B']],
        'IX' => ['Nine', 'নবম', 'secondary', 9, true, ['A', 'B']],
        'X' => ['Ten', 'দশম', 'secondary', 10, true, ['A', 'B']],
        'XI' => ['Eleven (HSC 1st year)', 'একাদশ', 'higher_secondary', 11, true, ['A']],
        'XII' => ['Twelve (HSC 2nd year)', 'দ্বাদশ', 'higher_secondary', 12, true, ['A']],
    ];

    /** code => [name, name_bn, type] */
    protected array $subjects = [
        '101' => ['Bangla 1st Paper', 'বাংলা ১ম পত্র', 'theory'],
        '102' => ['Bangla 2nd Paper', 'বাংলা ২য় পত্র', 'theory'],
        '107' => ['English 1st Paper', 'ইংরেজি ১ম পত্র', 'theory'],
        '108' => ['English 2nd Paper', 'ইংরেজি ২য় পত্র', 'theory'],
        '109' => ['Mathematics', 'গণিত', 'theory'],
        '154' => ['ICT', 'তথ্য ও যোগাযোগ প্রযুক্তি', 'theory_practical'],
        '111' => ['Religion & Moral Education', 'ধর্ম ও নৈতিক শিক্ষা', 'theory'],
        '150' => ['Bangladesh & Global Studies', 'বাংলাদেশ ও বিশ্বপরিচয়', 'theory'],
        '127' => ['Science', 'বিজ্ঞান', 'theory'],
        '136' => ['Physics', 'পদার্থবিজ্ঞান', 'theory_practical'],
        '137' => ['Chemistry', 'রসায়ন', 'theory_practical'],
        '138' => ['Biology', 'জীববিজ্ঞান', 'theory_practical'],
        '126' => ['Higher Mathematics', 'উচ্চতর গণিত', 'theory_practical'],
        '153' => ['History of Bangladesh & World Civilization', 'বাংলাদেশের ইতিহাস ও বিশ্বসভ্যতা', 'theory'],
        '110' => ['Geography & Environment', 'ভূগোল ও পরিবেশ', 'theory'],
        '141' => ['Economics', 'অর্থনীতি', 'theory'],
        '146' => ['Accounting', 'হিসাববিজ্ঞান', 'theory'],
        '143' => ['Business Entrepreneurship', 'ব্যবসায় উদ্যোগ', 'theory'],
        '152' => ['Finance & Banking', 'ফিন্যান্স ও ব্যাংকিং', 'theory'],
    ];

    protected array $groupSubjects = [
        'SCI' => ['136', '137', '138', '126'],
        'HUM' => ['153', '110', '141'],
        'BUS' => ['146', '143', '152'],
    ];

    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SSC')->first();
        if (! $institution) {
            $this->command?->warn('Demo institution not found — run DemoInstitutionSeeder first.');

            return;
        }

        $year = tenant()->runCentral($institution, fn () => AcademicYear::query()->firstOrCreate(
            ['name' => '2026'],
            ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_current' => true],
        ));

        $perBranch = max(0, (int) env('SEED_DEMO_STUDENTS_PER_BRANCH', 24));

        foreach (Branch::query()->where('institution_id', $institution->id)->with('institution')->ordered()->get() as $branch) {
            tenant()->runForBranch($branch, function () use ($branch, $year, $perBranch) {
                if (AcademicSession::query()->where('name', '2026')->exists()) {
                    return;
                }

                DB::transaction(function () use ($year, $perBranch) {
                    $session = AcademicSession::query()->create([
                        'academic_year_id' => $year->id, 'name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
                        'is_current' => true, 'status' => 'open',
                    ]);
                    foreach ([['Half-yearly', '2026-01-01', '2026-06-30', 1], ['Annual', '2026-07-01', '2026-12-31', 2]] as [$n, $s, $e, $o]) {
                        $session->terms()->create(['name' => $n, 'starts_on' => $s, 'ends_on' => $e, 'sort_order' => $o]);
                    }

                    $groups = collect([['SCI', 'Science', 'বিজ্ঞান'], ['HUM', 'Humanities', 'মানবিক'], ['BUS', 'Business Studies', 'ব্যবসায় শিক্ষা']])
                        ->mapWithKeys(fn ($g) => [$g[0] => StudyGroup::query()->create(['code' => $g[0], 'name' => $g[1], 'name_bn' => $g[2], 'is_active' => true])]);

                    $subjects = collect($this->subjects)->mapWithKeys(fn ($s, $code) => [$code => Subject::query()->create(
                        ['code' => (string) $code, 'name' => $s[0], 'name_bn' => $s[1], 'type' => $s[2], 'is_active' => true]
                    )]);

                    $roomNo = 101;
                    foreach ($this->classes as $code => [$name, $nameBn, $level, $order, $hasGroups, $sections]) {
                        $class = SchoolClass::query()->create([
                            'code' => $code, 'name' => $name, 'name_bn' => $nameBn, 'level' => $level,
                            'numeric_order' => $order, 'has_groups' => $hasGroups, 'is_active' => true,
                        ]);

                        foreach ($sections as $sectionName) {
                            $room = Room::query()->create(['code' => 'R'.$roomNo, 'name' => 'Room '.$roomNo, 'building' => 'Main', 'type' => 'classroom', 'capacity' => 45, 'is_active' => true]);
                            $roomNo++;
                            Section::query()->create(['school_class_id' => $class->id, 'name' => $sectionName, 'capacity' => 45, 'room_id' => $room->id, 'is_active' => true]);
                        }

                        $common = $hasGroups ? ['101', '102', '107', '108', '109', '154', '111'] : ['101', '102', '107', '108', '109', '154', '111', '150', '127'];
                        foreach ($common as $i => $subjectCode) {
                            ClassSubject::query()->create(['school_class_id' => $class->id, 'subject_id' => $subjects[$subjectCode]->id,
                                'category' => 'compulsory', 'full_marks' => 100, 'pass_marks' => 33, 'sort_order' => $i + 1]);
                        }
                        if ($hasGroups) {
                            foreach ($this->groupSubjects as $groupCode => $codes) {
                                foreach ($codes as $j => $subjectCode) {
                                    ClassSubject::query()->create(['school_class_id' => $class->id, 'subject_id' => $subjects[$subjectCode]->id,
                                        'study_group_id' => $groups[$groupCode]->id,
                                        'category' => ($groupCode === 'SCI' && $subjectCode === '126') ? 'optional' : 'compulsory',
                                        'full_marks' => 100, 'pass_marks' => 33, 'sort_order' => 20 + $j]);
                                }
                            }
                        }
                    }

                    $this->seedStudents($session, $groups, $perBranch);
                });
            });
        }

        $this->command?->info("Demo academic structure seeded ({$perBranch} students per branch).");
    }

    protected function seedStudents(AcademicSession $session, $groups, int $count): void
    {
        $faker = fake();
        $service = app(StudentService::class);
        $classes = SchoolClass::query()->with('sections')->ordered()->get();

        for ($i = 0; $i < $count; $i++) {
            $class = $classes[$i % $classes->count()];
            $section = $class->sections[$i % $class->sections->count()];
            $gender = $i % 2 ? 'female' : 'male';
            $age = 11 + ($class->numeric_order - 6);
            $surname = $faker->randomElement(['Rahman', 'Hossain', 'Islam', 'Ahmed', 'Khan', 'Chowdhury', 'Akter', 'Sarker', 'Das', 'Roy']);

            $service->admit(
                [
                    'name' => ($gender === 'male' ? $faker->firstNameMale() : $faker->firstNameFemale()).' '.$surname,
                    'gender' => $gender,
                    'date_of_birth' => now()->subYears($age)->subDays(random_int(0, 360))->toDateString(),
                    'blood_group' => $faker->randomElement(['A+', 'B+', 'O+', 'AB+']),
                    'religion' => $faker->randomElement(['Islam', 'Hinduism', 'Christianity', 'Buddhism']),
                    'nationality' => 'Bangladeshi',
                    'present_address' => $faker->streetAddress().', Dhaka',
                    'admission_date' => '2026-01-'.str_pad((string) random_int(2, 28), 2, '0', STR_PAD_LEFT),
                ],
                [[
                    'name' => $faker->firstNameMale().' '.$surname,
                    'phone' => '+8801'.random_int(3, 9).str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
                    'relation' => 'father',
                    'is_primary' => true,
                ]],
                [
                    'academic_session_id' => $session->id,
                    'school_class_id' => $class->id,
                    'section_id' => $section->id,
                    'study_group_id' => $class->has_groups ? $groups->values()[$i % 3]->id : null,
                    'enrolled_on' => '2026-01-05',
                ],
            );
        }
    }
}
