<?php

namespace Database\Seeders;

use App\Models\Assets\Category;
use App\Models\Branch;
use App\Models\HR\Employee;
use App\Models\Institution;
use App\Models\User;
use App\Services\Assets\AssetService;
use App\Services\Assets\DepreciationService;
use App\Services\Assets\MaintenanceService;
use App\Services\Finance\ChartOfAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo asset register for every demo branch (local/staging only): categories, about a dozen assets (furniture, IT, laboratory,
 * a vehicle, land) with hand-overs, maintenance tasks (one overdue calibration), an open repair, and depreciation charged up to
 * last month — all through the same services as the UI, so the register and the books agree.
 * Idempotent: a branch that already has asset categories is skipped.
 */
class DemoAssetsSeeder extends Seeder
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
                $admin = User::query()->where('email', "admin.branch{$branch->sort_order}@demo.test")->first();
                if (! $admin || Category::query()->exists()) {
                    return;
                }
                DB::transaction(fn () => $this->seedBranch($admin));
            });
        }
    }

    protected function seedBranch(User $admin): void
    {
        $chart = app(ChartOfAccounts::class);
        $chart->ensure();
        $assets = app(AssetService::class);
        $maintenance = app(MaintenanceService::class);
        $today = CarbonImmutable::today();
        $cash = $chart->id('cash');
        $bank = $chart->id('bank');
        $teacher = Employee::query()->orderBy('id')->first();

        $cat = [];
        foreach ([
            'Furniture' => ['general', 'straight_line', 120, 0, 0],
            'IT equipment' => ['general', 'straight_line', 60, 5, 0],
            'Laboratory equipment' => ['laboratory', 'straight_line', 96, 10, 0],
            'Vehicles' => ['general', 'declining_balance', 0, 10, 20],
            'Land & buildings' => ['general', 'none', 0, 0, 0],   // not depreciated
        ] as $name => [$kind, $method, $life, $salvage, $rate]) {
            $cat[$name] = Category::query()->create(['name' => $name, 'kind' => $kind, 'depreciation_method' => $method, 'useful_life_months' => $life,
                'salvage_percent' => $salvage, 'declining_rate' => $rate, 'is_active' => true]);
        }

        $buy = fn (string $name, string $category, float $cost, string $monthsAgo, array $extra = []) => $assets->register($extra + [
            'name' => $name, 'asset_category_id' => $cat[$category]->id, 'acquisition_type' => 'purchase', 'cost' => $cost, 'condition' => 'good',
            'acquired_on' => $today->subMonths((int) $monthsAgo)->startOfMonth()->addDays(4)->toDateString(), 'paid_from_account_id' => $bank ?: $cash,
        ], $admin);

        $tables = $buy('Teacher table', 'Furniture', 9500, '14');
        $buy('Student bench (set of 5)', 'Furniture', 22000, '14');
        $buy('Principal chair', 'Furniture', 18000, '9', ['condition' => 'new']);
        $projector = $buy('Multimedia projector', 'IT equipment', 52000, '10', ['brand' => 'Epson', 'model' => 'EB-X06', 'serial_no' => 'EPX06-7731', 'warranty_expires_on' => $today->addMonths(14)->toDateString()]);
        $laptop = $buy('Office laptop', 'IT equipment', 78000, '6', ['brand' => 'Dell', 'model' => 'Latitude 3420', 'serial_no' => 'DL-34-99120', 'condition' => 'new']);
        $buy('Desktop computer', 'IT equipment', 61000, '20', ['brand' => 'HP', 'serial_no' => 'HP-DT-4410']);
        $scope = $buy('Compound microscope', 'Laboratory equipment', 28000, '12', ['brand' => 'Olympus', 'serial_no' => 'OLY-CX23-118', 'safety_notes' => 'Keep covered; clean lenses with lens paper only.']);
        $balance = $buy('Analytical balance', 'Laboratory equipment', 46000, '8', ['brand' => 'Sartorius', 'serial_no' => 'SRT-A220-05']);
        $buy('School van', 'Vehicles', 2200000, '16', ['brand' => 'Toyota', 'model' => 'HiAce', 'serial_no' => 'DHA-METRO-CHA-1127']);
        $assets->register([
            'name' => 'School land (2 bigha)', 'asset_category_id' => $cat['Land & buildings']->id, 'acquisition_type' => 'donation', 'cost' => 4500000,
            'acquired_on' => $today->subYears(2)->toDateString(), 'condition' => 'good', 'description' => 'Donated by the founding committee.',
        ], $admin);

        // hand-overs
        $assets->allocate($projector, ['allocated_on' => $today->subMonths(5)->toDateString(), 'assignee' => 'Class Eight — Room 8', 'note' => 'Moves with the class timetable'], $admin);
        if ($teacher) {
            $assets->allocate($laptop, ['allocated_on' => $today->subMonths(5)->toDateString(), 'employee_id' => $teacher->id], $admin);
            $assets->allocate($tables, ['allocated_on' => $today->subMonths(12)->toDateString(), 'employee_id' => $teacher->id, 'expected_return_on' => $today->subDays(10)->toDateString()], $admin);   // overdue return
        }

        // maintenance: an overdue calibration, a task due soon, one completed, and an open repair that takes the projector offline
        $maintenance->createPlan($balance, ['title' => 'Annual calibration', 'kind' => 'calibration', 'interval_months' => 12, 'start_on' => $today->subDays(12)->toDateString(),
            'employee_id' => $teacher?->id]);
        $maintenance->createPlan($scope, ['title' => 'Lens cleaning & service', 'kind' => 'preventive', 'interval_months' => 6, 'start_on' => $today->addDays(5)->toDateString(),
            'employee_id' => $teacher?->id]);
        $done = $maintenance->createPlan($laptop, ['title' => 'Battery & fan inspection', 'kind' => 'inspection', 'interval_months' => 6, 'start_on' => $today->subDays(3)->toDateString()]);
        $maintenance->markPlanDone($done, ['completed_on' => $today->subDays(3)->toDateString(), 'cost' => 500, 'outcome' => 'Fan cleaned; battery at 92%.'], $admin);
        $repair = $maintenance->createLog($projector, ['kind' => 'repair', 'title' => 'Lamp replacement', 'description' => 'Image is dim and flickering.',
            'reported_on' => $today->subDays(2)->toDateString(), 'takes_offline' => true], $admin);
        $maintenance->start($repair);

        // depreciation up to last month
        $last = $today->subMonth()->format('Y-m');
        app(DepreciationService::class)->run($last, $admin);
    }
}
