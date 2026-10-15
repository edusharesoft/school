<?php

namespace Database\Seeders;

use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Admissions\AdmissionCampaign;
use App\Models\Branch;
use App\Models\Institution;
use App\Models\User;
use App\Services\Admissions\AdmissionService;
use Illuminate\Database\Seeder;

/**
 * Demo admission round per branch (local/staging only): an open Class Six admission with a test,
 * 12 applications (online + offline) with marks and a generated merit / waiting list.
 * Idempotent: a branch that already has an admission round is skipped.
 */
class DemoAdmissionSeeder extends Seeder
{
    protected array $names = [
        ['Ayesha Siddika', 'female'], ['Tanvir Hasan', 'male'], ['Nusrat Jahan', 'female'], ['Rakibul Islam', 'male'],
        ['Sadia Afrin', 'female'], ['Mehedi Hasan', 'male'], ['Jannatul Ferdous', 'female'], ['Arif Hossain', 'male'],
        ['Sumaiya Akter', 'female'], ['Imran Khan', 'male'], ['Tasnim Rahman', 'female'], ['Fahim Ahmed', 'male'],
    ];

    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SSC')->first();
        if (! $institution) {
            return;
        }

        foreach (Branch::query()->where('institution_id', $institution->id)->with('institution')->ordered()->get() as $branch) {
            tenant()->runForBranch($branch, function () use ($branch) {
                if (AdmissionCampaign::query()->exists()) {
                    return;
                }

                $session = AcademicSession::query()->current()->first();
                $class = SchoolClass::query()->where('code', 'VI')->first();
                $actor = User::query()->where('email', "admin.branch{$branch->sort_order}@demo.test")->first();
                if (! $session || ! $class || ! $actor) {
                    return;
                }

                mt_srand($branch->id * 104729);

                $campaign = AdmissionCampaign::query()->create([
                    'academic_session_id' => $session->id,
                    'school_class_id' => $class->id,
                    'title' => 'Class Six admission — '.$session->name,
                    'instructions' => "Bring the original birth certificate and two passport-size photos on the test day.\nAdmission test: Bangla, English and Mathematics (50 marks).",
                    'starts_on' => now()->subDays(10)->toDateString(),
                    'ends_on' => now()->addDays(20)->toDateString(),
                    'seats' => 8,
                    'is_online' => true,
                    'has_test' => true,
                    'test_at' => now()->addDays(25)->setTime(10, 0),
                    'test_venue' => 'Main building, room 101',
                    'test_total_marks' => 50,
                    'test_pass_marks' => 20,
                    'min_age' => 9,
                    'max_age' => 14,
                    'admission_fee' => 2500,
                    'fee_before_admission' => true,
                    'required_documents' => ['birth_certificate'],
                    'form_settings' => ['birth_registration_no' => 'required', 'photo' => 'optional', 'father_name' => 'required', 'mother_name' => 'required'],
                    'status' => 'open',
                ]);

                $service = app(AdmissionService::class);
                $marks = [];
                foreach ($this->names as $i => [$name, $gender]) {
                    $application = $service->submit($campaign, [
                        'name' => $name,
                        'gender' => $gender,
                        'date_of_birth' => sprintf('%d-%02d-%02d', (int) substr($session->name, 0, 4) - 12 + mt_rand(0, 1), mt_rand(1, 12), mt_rand(1, 28)),
                        'birth_registration_no' => (string) (20140000000000000 + $branch->id * 1000 + $i),
                        'phone' => '01'.mt_rand(3, 9).str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                        'present_address' => 'House '.mt_rand(1, 99).', Road '.mt_rand(1, 20).', Dhaka',
                        'father_name' => 'Md. '.explode(' ', $name)[1].' Uddin',
                        'mother_name' => 'Mst. Rahima Begum',
                        'guardian_name' => 'Md. '.explode(' ', $name)[1].' Uddin',
                        'guardian_phone' => '01'.mt_rand(3, 9).str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                        'guardian_relation' => 'father',
                        'previous_school' => 'Sunflower Primary School',
                    ], [], null, $i % 3 === 0 ? 'offline' : 'online', $i % 3 === 0 ? $actor : null);

                    $marks[$application->id] = $i === 11 ? ['absent' => 1] : ['marks' => mt_rand(14, 49)];
                }

                $service->recordMarks($campaign, $marks, $actor);
                $service->generateMerit($campaign, $actor);
            });
        }

        $this->command?->info('Demo admission rounds seeded.');
    }
}
