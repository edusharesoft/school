<?php

namespace Database\Seeders;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Institution;
use App\Models\User;
use App\Services\Tenancy\BranchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for local/staging: one institution, N branches (branch1…branchN),
 * one account per key role in every branch, and departments. Password for all demo
 * accounts: "password". Never runs in production (see DatabaseSeeder).
 */
class DemoInstitutionSeeder extends Seeder
{
    protected array $areas = ['Dhanmondi', 'Mirpur', 'Uttara', 'Gulshan', 'Motijheel', 'Mohammadpur', 'Banani', 'Bashundhara', 'Savar', 'Narayanganj',
        'Gazipur', 'Tongi', 'Keraniganj', 'Badda', 'Rampura'];

    protected array $areasBn = ['ধানমন্ডি', 'মিরপুর', 'উত্তরা', 'গুলশান', 'মতিঝিল', 'মোহাম্মদপুর', 'বনানী', 'বসুন্ধরা', 'সাভার', 'নারায়ণগঞ্জ',
        'গাজীপুর', 'টঙ্গী', 'কেরানীগঞ্জ', 'বাড্ডা', 'রামপুরা'];

    /** Approximate centre of each demo area [lat, lng] — powers the public "All Branch Locations" map. */
    protected array $coords = [[23.7461, 90.3742], [23.8223, 90.3654], [23.8759, 90.3795], [23.7925, 90.4078], [23.7330, 90.4172],
        [23.7600, 90.3589], [23.7937, 90.4066], [23.8193, 90.4526], [23.8583, 90.2667], [23.6238, 90.5000],
        [24.0023, 90.4203], [23.8924, 90.4048], [23.6970, 90.3550], [23.7808, 90.4260], [23.7594, 90.4210]];

    public function run(): void
    {
        $count = max(1, min(15, (int) env('SEED_DEMO_BRANCHES', 10)));

        tenant()->runCentral(null, function () use ($count) {
            DB::transaction(function () use ($count) {
                $institution = Institution::query()->firstOrCreate(['code' => 'SSC'], [
                    'name' => 'Shataj School & College',
                    'name_bn' => 'শাতাজ স্কুল অ্যান্ড কলেজ',
                    'slug' => 'shataj',
                    'email' => 'info@schooldomain.com',
                    'phone' => '+8801700000000',
                    'address' => 'Dhaka, Bangladesh',
                    'primary_color' => '#1f6f5c',
                    'timezone' => 'Asia/Dhaka',
                    'currency' => 'BDT',
                    'locale' => 'en',
                    'status' => 'active',
                ]);

                $this->user($institution, 'Institution Admin', 'admin@demo.test', UserType::Staff, ['institution-admin'], []);
                $this->user($institution, 'Chief Auditor', 'auditor@demo.test', UserType::Staff, ['auditor'], []);

                $service = app(BranchService::class);

                for ($i = 1; $i <= $count; $i++) {
                    $area = $this->areas[$i - 1];
                    $branch = Branch::query()->where('subdomain', "branch{$i}")->first()
                        ?? $service->create($institution, [
                            'name' => "Shataj School & College, {$area}",
                            'name_bn' => "শাতাজ স্কুল অ্যান্ড কলেজ, {$this->areasBn[$i - 1]}",
                            'code' => sprintf('BR%02d', $i),
                            'subdomain' => "branch{$i}",
                            'type' => 'school_college',
                            'email' => "branch{$i}@schooldomain.com",
                            'phone' => sprintf('+88017000000%02d', $i),
                            'address' => "{$area}, Dhaka",
                            'principal_name' => "Principal {$area}",
                            'timezone' => 'Asia/Dhaka',
                            'currency' => 'BDT',
                            'locale' => 'en',
                            'status' => 'active',
                            'sort_order' => $i,
                            'latitude' => $this->coords[$i - 1][0],
                            'longitude' => $this->coords[$i - 1][1],
                        ]);

                    $b = [$branch->id];
                    $this->user($institution, "Branch Admin {$i}", "admin.branch{$i}@demo.test", UserType::Staff, ['branch-admin'], $b);
                    $this->user($institution, "Principal {$i}", "principal.branch{$i}@demo.test", UserType::Staff, ['principal'], $b);
                    $this->user($institution, "Accountant {$i}", "accounts.branch{$i}@demo.test", UserType::Staff, ['accountant'], $b);
                    $this->user($institution, "Teacher {$i}", "teacher.branch{$i}@demo.test", UserType::Teacher, ['teacher'], $b);
                    $this->user($institution, "Class Teacher {$i}", "classteacher.branch{$i}@demo.test", UserType::Teacher, ['class-teacher'], $b);
                    $this->user($institution, "Student {$i}", "student.branch{$i}@demo.test", UserType::Student, ['student'], $b);
                    $this->user($institution, "Guardian {$i}", "parent.branch{$i}@demo.test", UserType::Parent, ['parent'], $b);

                    foreach ([['SCI', 'Science', 'বিজ্ঞান'], ['HUM', 'Humanities', 'মানবিক'], ['BUS', 'Business Studies', 'ব্যবসায় শিক্ষা'],
                        ['ADM', 'Administration', 'প্রশাসন'], ['ACC', 'Accounts', 'হিসাব']] as [$code, $name, $nameBn]) {
                        if (! Department::withoutTenancy()->where('branch_id', $branch->id)->where('code', $code)->exists()) {
                            $department = new Department(['name' => $name, 'name_bn' => $nameBn, 'code' => $code, 'is_active' => true]);
                            $department->branch_id = $branch->id;
                            $department->save();
                        }
                    }
                }

                // One HR manager with access to two branches, to demonstrate branch switching.
                $multi = Branch::query()->whereIn('subdomain', ['branch1', 'branch2'])->pluck('id')->all();
                $this->user($institution, 'Regional HR Manager', 'hr@demo.test', UserType::Staff, ['hr-manager'], $multi);
            });
        });

        $this->command?->info("Demo institution seeded with {$count} branches. Demo password for every account: password");
    }

    protected function user(Institution $institution, string $name, string $email, UserType $type, array $roles, array $branchIds): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $user->fill(['name' => $name, 'user_type' => $type, 'status' => 'active', 'password' => 'password']);
            $user->institution_id = $institution->id;
            $user->default_branch_id = $branchIds[0] ?? null;
            $user->email_verified_at = now();
            $user->save();
        }

        $user->syncRoles($roles);
        $sync = [];
        foreach ($branchIds as $idx => $id) {
            $sync[$id] = ['is_default' => $idx === 0];
        }
        $user->branches()->syncWithoutDetaching($sync);

        return $user;
    }
}
