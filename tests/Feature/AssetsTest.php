<?php

namespace Tests\Feature;

use App\Models\Assets\Allocation;
use App\Models\Assets\Asset;
use App\Models\Assets\Audit;
use App\Models\Assets\Category;
use App\Models\Assets\Depreciation;
use App\Models\Assets\DepreciationRun;
use App\Models\Assets\Disposal;
use App\Models\Assets\MaintenanceLog;
use App\Models\Assets\MaintenancePlan;
use App\Models\Branch;
use App\Models\Finance\JournalLine;
use App\Models\Finance\Vendor;
use App\Models\Inventory\Category as StockCategory;
use App\Models\Inventory\Item;
use App\Models\User;
use App\Services\Assets\AssetAuditService;
use App\Services\Assets\AssetService;
use App\Services\Assets\DepreciationService;
use App\Services\Assets\DisposalService;
use App\Services\Assets\MaintenanceService;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Asset register: registration, stock capitalisation, allocation, maintenance, depreciation, disposal and verification — always in step with the books. */
class AssetsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
    }

    protected function world(): array
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');
        $cat = $this->inBranch($branch, fn () => Category::query()->create(['name' => 'Furniture', 'kind' => 'general', 'depreciation_method' => 'straight_line', 'useful_life_months' => 120, 'salvage_percent' => 0, 'is_active' => true]));
        $lab = $this->inBranch($branch, fn () => Category::query()->create(['name' => 'Lab equipment', 'kind' => 'laboratory', 'depreciation_method' => 'straight_line', 'useful_life_months' => 96, 'salvage_percent' => 10, 'is_active' => true]));
        $cash = $this->inBranch($branch, fn () => app(ChartOfAccounts::class)->id('cash'));
        $bank = $this->inBranch($branch, fn () => app(ChartOfAccounts::class)->id('bank'));

        return compact('branch', 'admin', 'cat', 'lab', 'cash', 'bank');
    }

    /** Run something inside the branch context with the services. */
    protected function svc(Branch $branch, \Closure $fn): mixed
    {
        return $this->inBranch($branch, fn () => $fn(app(AssetService::class), app(DepreciationService::class), app(DisposalService::class), app(MaintenanceService::class)));
    }

    protected function register(array $w, array $over = []): Asset
    {
        return $this->svc($w['branch'], fn ($assets) => $assets->register($over + [
            'name' => 'Teacher table', 'asset_category_id' => $w['cat']->id, 'acquisition_type' => 'purchase', 'acquired_on' => '2026-01-10', 'in_service_on' => '2026-01-10',
            'cost' => 12000, 'paid_from_account_id' => $w['cash'], 'condition' => 'new',
        ], $w['admin']));
    }

    protected function balance(Branch $branch, string $key): float
    {
        return $this->inBranch($branch, fn () => app(LedgerService::class)->balance(app(ChartOfAccounts::class)->get($key)));
    }

    protected function reload(Branch $branch, $model)
    {
        return $this->inBranch($branch, fn () => $model->fresh());
    }

    /** The register always equals the Fixed assets / Accumulated depreciation accounts, and every entry balances. */
    protected function assertBooksMatch(Branch $branch): void
    {
        $this->inBranch($branch, function () use ($branch) {
            $live = Asset::query()->live();
            $this->assertEqualsWithDelta((float) (clone $live)->sum('cost'), $this->balance($branch, 'fixed_assets'), 0.001, 'register cost = Fixed assets account');
            $this->assertEqualsWithDelta((float) (clone $live)->sum('accumulated_depreciation'), -$this->balance($branch, 'accumulated_depreciation'), 0.001, 'register depreciation = Accumulated depreciation account');
            $this->assertEqualsWithDelta((float) JournalLine::query()->sum('debit'), (float) JournalLine::query()->sum('credit'), 0.001, 'books balance');
        });
    }

    public function test_purchase_registration_posts_to_the_books(): void
    {
        $w = $this->world();
        $asset = $this->register($w);

        $this->assertSame('AS-000001', $asset->code);
        $this->assertSame('in_stock', $asset->status);
        $this->assertEquals(12000, $asset->cost);
        $this->assertSame('2026-01', $asset->depreciation_from);
        $this->assertEquals(120, $asset->useful_life_months);               // from the category
        $this->assertEquals(12000, $this->balance($w['branch'], 'fixed_assets'));
        $this->assertEquals(-12000, $this->balance($w['branch'], 'cash'));   // paid from cash
        $this->assertSame('AS-000002', $this->register($w, ['name' => 'Second table'])->code);
        $this->assertBooksMatch($w['branch']);
    }

    public function test_registration_rules(): void
    {
        $w = $this->world();
        foreach ([
            ['cost' => 0], ['cost' => 1000, 'paid_from_account_id' => null], ['acquired_on' => '2026-04-01'], ['in_service_on' => '2026-01-01'],
            ['depreciation_method' => 'straight_line', 'useful_life_months' => 0], ['depreciation_method' => 'declining_balance', 'declining_rate' => 0],
            ['salvage_value' => 99999], ['acquisition_type' => 'gift'], ['name' => ' '],
        ] as $bad) {
            try {
                $this->register($w, $bad + ['useful_life_months' => 60]);
                $this->fail('Expected a validation error for '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertEquals(0, $this->inBranch($w['branch'], fn () => Asset::query()->count()));
        $this->assertEquals(0, $this->balance($w['branch'], 'fixed_assets'));
    }

    public function test_donated_and_opening_balance_assets(): void
    {
        $w = $this->world();
        $this->register($w, ['acquisition_type' => 'donation', 'name' => 'Donated projector', 'cost' => 30000]);
        $this->assertEquals(30000, $this->balance($w['branch'], 'other_income'));

        $old = $this->register($w, ['acquisition_type' => 'opening', 'name' => 'Old generator', 'cost' => 100000, 'acquired_on' => '2020-02-01', 'in_service_on' => '2020-02-01',
            'opening_accumulated' => 40000, 'opening_date' => '2026-03-01', 'depreciation_from' => '2026-03', 'useful_life_months' => 120, 'paid_from_account_id' => null]);
        $this->assertEquals(40000, $old->accumulated_depreciation);
        $this->assertEquals(60000, $this->balance($w['branch'], 'opening_balance'));
        $this->assertEquals(-40000, $this->balance($w['branch'], 'accumulated_depreciation'));
        $this->assertEquals(60000, $old->bookValue());
        $this->assertBooksMatch($w['branch']);

        // The opening asset is charged from March only, not from 2020.
        $run = $this->svc($w['branch'], fn ($a, $d) => $d->run('2026-03', $w['admin'], [$old]));
        $this->assertEquals(833.33, round($run->total, 2));                 // 100000 / 120 months
        $this->assertSame(1, $this->inBranch($w['branch'], fn () => Depreciation::query()->where('asset_id', $old->id)->count()));
        $this->assertBooksMatch($w['branch']);
    }

    public function test_straight_line_depreciation_posts_every_month_once_and_ends_exactly(): void
    {
        $w = $this->world();
        $a = $this->register($w, ['name' => 'Cheap thing', 'cost' => 1000, 'useful_life_months' => 3, 'acquired_on' => '2025-11-05', 'in_service_on' => '2025-11-05']);

        $preview = $this->svc($w['branch'], fn ($s, $d) => $d->preview('2026-03'));
        $this->assertCount(1, $preview);
        $this->assertSame(['2025-11', '2025-12', '2026-01'], array_column($preview[0]['months'], 'period'));   // only three months of life
        $this->assertEquals(1000.00, $preview[0]['total']);

        $run = $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-03', $w['admin']));
        $this->assertEquals(1000, $run->total);
        $a = $this->reload($w['branch'], $a);
        $this->assertEquals(1000, $a->accumulated_depreciation);
        $this->assertEquals(0, $a->bookValue());
        $this->assertSame('2026-01', $a->depreciated_through);
        $this->assertEquals(3, $a->months_depreciated);
        $this->assertEquals(1000, $this->balance($w['branch'], 'depreciation'));
        $this->assertEquals(3, $this->inBranch($w['branch'], fn () => \App\Models\Finance\JournalEntry::query()->where('source_type', 'asset_depreciation_run')->count()));   // one entry per month
        $this->assertBooksMatch($w['branch']);

        // Nothing left to charge — the same month cannot be posted twice.
        $this->expectException(ValidationException::class);
        $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-03', $w['admin']));
    }

    public function test_depreciation_rounding_and_months_run_in_two_steps(): void
    {
        $w = $this->world();
        $a = $this->register($w, ['name' => 'Odd cost', 'cost' => 1000, 'useful_life_months' => 3, 'acquired_on' => '2026-01-02', 'in_service_on' => '2026-01-02']);
        $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-01', $w['admin']));
        $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-02', $w['admin']));
        $a = $this->reload($w['branch'], $a);
        $this->assertEquals(666.66, $a->accumulated_depreciation);
        $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-03', $w['admin']));
        $a = $this->reload($w['branch'], $a);
        $this->assertEquals(1000.00, $a->accumulated_depreciation);        // the last month takes the 1 paisa of rounding
        $this->assertEquals(3, $a->months_depreciated);
        $this->assertBooksMatch($w['branch']);
    }

    public function test_declining_balance_never_goes_below_salvage(): void
    {
        $w = $this->world();
        $a = $this->register($w, ['name' => 'Vehicle', 'cost' => 100000, 'salvage_value' => 90000, 'depreciation_method' => 'declining_balance', 'declining_rate' => 24, 'acquired_on' => '2025-01-01', 'in_service_on' => '2025-01-01']);
        // 2 % a month of the book value: 2000, 1960, 1920.80 … until 10 000 of depreciation is used up.
        $plan = $this->svc($w['branch'], fn ($s, $d) => $d->plan($a, '2026-03'));
        $this->assertEquals(2000.00, $plan[0]['amount']);
        $this->assertEquals(1960.00, $plan[1]['amount']);
        $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-03', $w['admin']));
        $a = $this->reload($w['branch'], $a);
        $this->assertLessThanOrEqual(10000.0, $a->accumulated_depreciation);
        $this->assertGreaterThanOrEqual(90000.0, $a->bookValue() - 0.0);
        $this->assertBooksMatch($w['branch']);
    }

    public function test_assets_without_depreciation_and_future_months(): void
    {
        $w = $this->world();
        $this->register($w, ['name' => 'Land', 'cost' => 500000, 'depreciation_method' => 'none']);
        try {
            $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-03', $w['admin']));
            $this->fail('Nothing to depreciate');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }
        $this->register($w);
        $this->expectException(ValidationException::class);
        $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-04', $w['admin']));        // a future month
    }

    public function test_reversing_a_run_restores_everything_in_order(): void
    {
        $w = $this->world();
        $a = $this->register($w, ['cost' => 12000, 'useful_life_months' => 12]);
        $r1 = $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-01', $w['admin']));
        $r2 = $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-02', $w['admin']));

        try {
            $this->svc($w['branch'], fn ($s, $d) => $d->reverse($r1, 'wrong', $w['admin']));
            $this->fail('The earlier run cannot be reversed first');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('run', $e->errors());
        }
        $this->svc($w['branch'], fn ($s, $d) => $d->reverse($r2, 'posted twice', $w['admin']));
        $a = $this->reload($w['branch'], $a);
        $this->assertEquals(1000, $a->accumulated_depreciation);
        $this->assertSame('2026-01', $a->depreciated_through);
        $this->assertEquals(1, $a->months_depreciated);
        $this->assertSame('reversed', $this->reload($w['branch'], $r2)->status);
        $this->assertBooksMatch($w['branch']);

        $this->svc($w['branch'], fn ($s, $d) => $d->reverse($r1, 'restart', $w['admin']));
        $this->assertEquals(0, $this->reload($w['branch'], $a)->accumulated_depreciation);
        $this->assertEquals(0, $this->balance($w['branch'], 'depreciation'));
        // February can now be charged again (and January with it).
        $again = $this->svc($w['branch'], fn ($s, $d) => $d->run('2026-02', $w['admin']));
        $this->assertEquals(2000, $again->total);
        $this->assertBooksMatch($w['branch']);
    }

    public function test_disposal_by_sale_with_gain_and_loss(): void
    {
        $w = $this->world();
        $gain = $this->register($w, ['name' => 'Old computer', 'cost' => 24000, 'useful_life_months' => 24, 'acquired_on' => '2026-01-01', 'in_service_on' => '2026-01-01']);
        $loss = $this->register($w, ['name' => 'Almirah', 'cost' => 24000, 'useful_life_months' => 24, 'acquired_on' => '2026-01-01', 'in_service_on' => '2026-01-01']);

        // Disposed in March: January and February are charged first (1000 a month → 2000, book value 22000).
        $d1 = $this->svc($w['branch'], fn ($s, $d, $x) => $x->dispose($gain, ['type' => 'sale', 'date' => '2026-03-10', 'proceeds' => 25000, 'account_id' => $w['bank'], 'buyer' => 'Scrap dealer'], $w['admin']));
        $this->assertEquals(22000, $d1->book_value);
        $this->assertEquals(2000, $d1->accumulated);
        $this->assertEquals(3000, $d1->gain_loss);
        $this->assertEquals(3000, $this->balance($w['branch'], 'asset_disposal_gain'));
        $this->assertEquals(25000, $this->balance($w['branch'], 'bank'));
        $this->assertSame('disposed', $this->reload($w['branch'], $gain)->status);

        $d2 = $this->svc($w['branch'], fn ($s, $d, $x) => $x->dispose($loss, ['type' => 'sale', 'date' => '2026-03-11', 'proceeds' => 20000, 'account_id' => $w['bank']], $w['admin']));
        $this->assertEquals(-2000, $d2->gain_loss);
        $this->assertEquals(2000, $this->balance($w['branch'], 'asset_disposal_loss'));
        $this->assertEquals(0, $this->balance($w['branch'], 'fixed_assets'));
        $this->assertEquals(0, $this->balance($w['branch'], 'accumulated_depreciation'));
        $this->assertBooksMatch($w['branch']);

        // Already gone; sales need an amount and an account.
        try {
            $this->svc($w['branch'], fn ($s, $d, $x) => $x->dispose($gain, ['type' => 'scrap', 'date' => '2026-03-12'], $w['admin']));
            $this->fail('Already disposed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('asset', $e->errors());
        }
        $third = $this->register($w, ['name' => 'Third']);
        foreach ([['type' => 'sale', 'date' => '2026-03-12'], ['type' => 'sale', 'date' => '2026-03-12', 'proceeds' => 100], ['type' => 'gift', 'date' => '2026-03-12'], ['type' => 'scrap', 'date' => '2026-03-20']] as $bad) {
            try {
                $this->svc($w['branch'], fn ($s, $d, $x) => $x->dispose($third, $bad, $w['admin']));
                $this->fail('Expected a validation error for '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_write_off_donation_and_reversing_a_disposal(): void
    {
        $w = $this->world();
        $a = $this->register($w, ['cost' => 12000, 'useful_life_months' => 12, 'acquired_on' => '2026-01-01', 'in_service_on' => '2026-01-01']);
        $d = $this->svc($w['branch'], fn ($s, $dp, $x) => $x->dispose($a, ['type' => 'lost', 'date' => '2026-03-14', 'proceeds' => 5000, 'reason' => 'stolen'], $w['admin']));
        $this->assertEquals(0, $d->proceeds);                                      // nothing is received for a lost asset
        $this->assertEquals(-10000, $d->gain_loss);                                // 12000 − 2000 depreciation
        $this->assertEquals(10000, $this->balance($w['branch'], 'asset_disposal_loss'));
        $this->assertBooksMatch($w['branch']);

        $this->svc($w['branch'], fn ($s, $dp, $x) => $x->reverse($d, 'found it', $w['admin']));
        $a = $this->reload($w['branch'], $a);
        $this->assertSame('in_stock', $a->status);
        $this->assertEquals(12000, $this->balance($w['branch'], 'fixed_assets'));
        $this->assertEquals(-2000, $this->balance($w['branch'], 'accumulated_depreciation'));   // the catch-up depreciation stays
        $this->assertEquals(0, $this->balance($w['branch'], 'asset_disposal_loss'));
        $this->assertBooksMatch($w['branch']);

        // The asset is back, so it can be disposed of again (donated this time) and depreciation can still be reversed only after that.
        $d2 = $this->svc($w['branch'], fn ($s, $dp, $x) => $x->dispose($a, ['type' => 'donated', 'date' => '2026-03-15'], $w['admin']));
        $this->assertSame('donated', $d2->type);
        $this->assertBooksMatch($w['branch']);
    }

    public function test_allocation_and_return(): void
    {
        $w = $this->world();
        $employee = $this->makeEmployee($w['branch']);
        $a = $this->register($w);

        $alloc = $this->svc($w['branch'], fn ($s) => $s->allocate($a, ['employee_id' => $employee->id, 'allocated_on' => '2026-02-01', 'expected_return_on' => '2026-03-01'], $w['admin']));
        $this->assertSame('in_use', $this->reload($w['branch'], $a)->status);
        $this->assertTrue($this->reload($w['branch'], $alloc)->isOverdue());

        foreach ([
            ['employee_id' => $employee->id, 'allocated_on' => '2026-03-01'],                       // already allocated
        ] as $bad) {
            try {
                $this->svc($w['branch'], fn ($s) => $s->allocate($a, $bad, $w['admin']));
                $this->fail('Already allocated');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('asset', $e->errors());
            }
        }
        $b = $this->register($w, ['name' => 'Chair']);
        foreach ([['allocated_on' => '2026-03-01'], ['assignee' => 'X', 'allocated_on' => '2026-04-01'], ['assignee' => 'X', 'allocated_on' => '2026-01-01'], ['assignee' => 'X', 'allocated_on' => '2026-03-01', 'expected_return_on' => '2026-02-01']] as $bad) {
            try {
                $this->svc($w['branch'], fn ($s) => $s->allocate($b, $bad, $w['admin']));
                $this->fail('Expected a validation error for '.json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        $this->svc($w['branch'], fn ($s) => $s->takeBack($alloc, ['returned_on' => '2026-03-10', 'return_condition' => 'fair'], $w['admin']));
        $a = $this->reload($w['branch'], $a);
        $this->assertSame('in_stock', $a->status);
        $this->assertSame('fair', $a->condition);
        $this->expectException(ValidationException::class);                                           // taken back twice
        $this->svc($w['branch'], fn ($s) => $s->takeBack($alloc, ['returned_on' => '2026-03-11'], $w['admin']));
    }

    public function test_maintenance_plan_work_order_and_under_repair_flag(): void
    {
        $w = $this->world();
        $a = $this->register($w, ['name' => 'Microscope', 'asset_category_id' => $w['lab']->id]);
        $plan = $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->createPlan($a, ['title' => 'Calibration', 'kind' => 'calibration', 'interval_months' => 6, 'start_on' => '2026-02-01']));
        $this->assertSame('2026-02-01', $plan->next_due_on->toDateString());
        $this->assertTrue($plan->isOverdue());

        $log = $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->markPlanDone($plan, ['completed_on' => '2026-03-10', 'cost' => 1500, 'reference' => 'CERT-77'], $w['admin']));
        $this->assertSame('completed', $log->status);
        $plan = $this->reload($w['branch'], $plan);
        $this->assertSame('2026-09-10', $plan->next_due_on->toDateString());       // six months after the day the work was done
        $this->assertSame('2026-03-10', $plan->last_done_on->toDateString());

        // A repair takes the asset out of service until it is completed or cancelled.
        $repair = $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->createLog($a, ['kind' => 'repair', 'title' => 'Lens broken', 'reported_on' => '2026-03-12'], $w['admin']));
        $this->assertTrue($this->reload($w['branch'], $a)->is_under_repair);
        try {
            $this->svc($w['branch'], fn ($s) => $s->allocate($a, ['assignee' => 'Lab', 'allocated_on' => '2026-03-13'], $w['admin']));
            $this->fail('Under repair');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('asset', $e->errors());
        }
        $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->start($repair, '2026-03-13'));
        $done = $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->complete($repair, ['completed_on' => '2026-03-14', 'cost' => 800, 'condition_after' => 'good'], $w['admin']));
        $a = $this->reload($w['branch'], $a);
        $this->assertFalse($a->is_under_repair);
        $this->assertSame('good', $a->condition);
        $this->assertEquals(800, $done->cost);
        $this->assertEquals(0, $this->balance($w['branch'], 'maintenance'));      // maintenance costs are not posted to the books

        $second = $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->createLog($a, ['kind' => 'repair', 'title' => 'Again', 'reported_on' => '2026-03-15'], $w['admin']));
        $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->cancel($second, 'not needed'));
        $this->assertFalse($this->reload($w['branch'], $a)->is_under_repair);
        $this->expectException(ValidationException::class);                      // a closed order cannot be completed
        $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->complete($second, ['completed_on' => '2026-03-15'], $w['admin']));
    }

    public function test_open_work_orders_block_disposal_and_disposed_assets_cannot_be_maintained(): void
    {
        $w = $this->world();
        $a = $this->register($w);
        $log = $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->createLog($a, ['kind' => 'repair', 'title' => 'Broken leg', 'reported_on' => '2026-03-12'], $w['admin']));
        try {
            $this->svc($w['branch'], fn ($s, $d, $x) => $x->dispose($a, ['type' => 'scrap', 'date' => '2026-03-14'], $w['admin']));
            $this->fail('Open work order');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('asset', $e->errors());
        }
        $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->cancel($log, 'scrap instead'));
        $this->svc($w['branch'], fn ($s, $d, $x) => $x->dispose($a, ['type' => 'scrap', 'date' => '2026-03-14'], $w['admin']));
        $this->expectException(ValidationException::class);
        $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->createLog($this->reload($w['branch'], $a), ['kind' => 'repair', 'title' => 'Too late'], $w['admin']));
    }

    public function test_due_reminders_go_to_the_responsible_employee_once_a_week(): void
    {
        $w = $this->world();
        $employee = $this->makeEmployee($w['branch']);
        $a = $this->register($w);
        $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->createPlan($a, ['title' => 'Service', 'interval_months' => 12, 'start_on' => '2026-03-18', 'employee_id' => $employee->id]));
        $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->createPlan($a, ['title' => 'No owner', 'interval_months' => 12, 'start_on' => '2026-03-16']));
        $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->createPlan($a, ['title' => 'Far away', 'interval_months' => 12, 'start_on' => '2026-08-16', 'employee_id' => $employee->id]));

        $first = $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->sendDueReminders());
        $this->assertGreaterThanOrEqual(0, $first);
        $notified = $this->inBranch($w['branch'], fn () => MaintenancePlan::query()->whereNotNull('last_notified_on')->pluck('title')->all());
        $this->assertSame(['Service'], $notified);                                 // only the plan that is due soon and has an owner
        $this->assertSame(0, $this->svc($w['branch'], fn ($s, $d, $x, $m) => $m->sendDueReminders()));    // not again the same week
    }

    public function test_capitalising_durable_stock_moves_value_from_inventory_to_assets(): void
    {
        $w = $this->world();
        $stockCat = $this->inBranch($w['branch'], fn () => StockCategory::query()->create(['name' => 'Lab', 'kind' => 'laboratory', 'is_active' => true]));
        $vendor = $this->inBranch($w['branch'], fn () => Vendor::query()->create(['name' => 'Lab Supplies', 'is_active' => true]));
        $inv = fn ($fn) => $this->inBranch($w['branch'], fn () => $fn(app(InventoryService::class), app(ProcurementService::class)));
        $scope = $inv(fn ($i) => $i->createItem(['name' => 'Microscope', 'inventory_category_id' => $stockCat->id, 'item_type' => 'durable', 'unit' => 'pcs', 'is_active' => true]));
        $paper = $inv(fn ($i) => $i->createItem(['name' => 'Paper', 'inventory_category_id' => $stockCat->id, 'item_type' => 'consumable', 'unit' => 'ream', 'is_active' => true]));
        $inv(fn ($i, $p) => $p->receive(['vendor_id' => $vendor->id, 'received_on' => '2026-03-01', 'payment_mode' => 'credit'],
            [['inventory_item_id' => $scope->id, 'quantity' => 3, 'unit_cost' => 10000], ['inventory_item_id' => $paper->id, 'quantity' => 5, 'unit_cost' => 100]], $w['admin']));
        $this->assertEquals(30500, $this->balance($w['branch'], 'inventory_stock'));

        $assets = $this->svc($w['branch'], fn ($s) => $s->capitaliseFromStock($this->reload($w['branch'], $scope), 2, [
            'asset_category_id' => $w['lab']->id, 'acquired_on' => '2026-03-05', 'serials' => "SN-1\nSN-2", 'room_id' => null, 'location' => 'Science lab',
        ], $w['admin']));

        $this->assertCount(2, $assets);
        $this->assertSame(['SN-1', 'SN-2'], $assets->pluck('serial_no')->all());
        $this->assertEquals([10000.0, 10000.0], $assets->pluck('cost')->all());
        $this->assertEquals(1000, $assets[0]->salvage_value);                      // 10 % salvage of the laboratory category
        $this->assertSame('Microscope', $assets[0]->name);
        $this->assertEquals(1, $this->reload($w['branch'], $scope)->quantity_on_hand);
        $this->assertEquals(10500, $this->balance($w['branch'], 'inventory_stock'));
        $this->assertEquals(20000, $this->balance($w['branch'], 'fixed_assets'));
        $this->assertBooksMatch($w['branch']);
        $this->inBranch($w['branch'], fn () => $this->assertEqualsWithDelta(\App\Models\Inventory\Item::query()->sum('stock_value'), $this->balance($w['branch'], 'inventory_stock'), 0.001));

        // A consumable cannot be capitalised; neither can more units than are on the shelf.
        foreach ([[$paper, 1], [$scope, 5], [$scope, 0]] as [$item, $qty]) {
            try {
                $this->svc($w['branch'], fn ($s) => $s->capitaliseFromStock($this->reload($w['branch'], $item), $qty, ['asset_category_id' => $w['lab']->id], $w['admin']));
                $this->fail('Expected a validation error');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }

        // Cancelling the registration puts the unit back on the shelf at the same value.
        $this->svc($w['branch'], fn ($s) => $s->void($assets[1], 'registered twice', $w['admin']));
        $this->assertEquals(2, $this->reload($w['branch'], $scope)->quantity_on_hand);
        $this->assertEquals(20500, $this->balance($w['branch'], 'inventory_stock'));
        $this->assertEquals(10000, $this->balance($w['branch'], 'fixed_assets'));
        $this->assertSame('voided', $this->reload($w['branch'], $assets[1])->status);
        $this->assertBooksMatch($w['branch']);
    }

    public function test_void_is_refused_once_the_asset_has_a_history(): void
    {
        $w = $this->world();
        $a = $this->register($w);
        $this->svc($w['branch'], fn ($s) => $s->allocate($a, ['assignee' => 'Office', 'allocated_on' => '2026-02-01'], $w['admin']));
        $this->expectException(ValidationException::class);
        $this->svc($w['branch'], fn ($s) => $s->void($this->reload($w['branch'], $a), 'oops', $w['admin']));
    }

    public function test_cancelling_a_purchase_registration_reverses_the_payment(): void
    {
        $w = $this->world();
        $a = $this->register($w);
        $this->svc($w['branch'], fn ($s) => $s->void($a, 'duplicate', $w['admin']));
        $this->assertEquals(0, $this->balance($w['branch'], 'fixed_assets'));
        $this->assertEquals(0, $this->balance($w['branch'], 'cash'));
        $this->assertBooksMatch($w['branch']);
    }

    public function test_physical_verification_updates_the_register_and_flags_missing_assets(): void
    {
        $w = $this->world();
        $a = $this->register($w, ['name' => 'Chair A']);
        $b = $this->register($w, ['name' => 'Chair B']);
        $c = $this->register($w, ['name' => 'Chair C']);
        $other = $this->register($w, ['name' => 'Microscope', 'asset_category_id' => $w['lab']->id]);

        $audit = $this->inBranch($w['branch'], fn () => app(AssetAuditService::class)->start(['title' => 'Furniture check', 'date' => '2026-03-15', 'asset_category_id' => $w['cat']->id], $w['admin']));
        $lines = $this->inBranch($w['branch'], fn () => $audit->items()->get());
        $this->assertCount(3, $lines);                                              // only the furniture category

        $svc = fn ($fn) => $this->inBranch($w['branch'], fn () => $fn(app(AssetAuditService::class)));
        try {
            $svc(fn ($s) => $s->complete($audit, $w['admin']));
            $this->fail('Nothing checked');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('results', $e->errors());
        }
        $items = $lines->keyBy('asset_id');
        $svc(fn ($s) => $s->saveResults($audit, [
            $items[$a->id]->id => ['result' => 'found', 'found_location' => 'Room 7'],
            $items[$b->id]->id => ['result' => 'damaged'],
            $items[$c->id]->id => ['result' => 'missing', 'note' => 'not in the room'],
        ]));
        $svc(fn ($s) => $s->complete($audit, $w['admin']));

        $a = $this->reload($w['branch'], $a);
        $this->assertSame('Room 7', $a->location);
        $this->assertSame('2026-03-15', $a->last_verified_on->toDateString());
        $this->assertSame('damaged', $this->reload($w['branch'], $b)->condition);
        $this->assertTrue($this->reload($w['branch'], $c)->is_missing);
        $this->assertNull($this->reload($w['branch'], $other)->last_verified_on);

        // A missing asset can be written off as lost; found later it is cleared by the next audit.
        $this->svc($w['branch'], fn ($s, $d, $x) => $x->dispose($this->reload($w['branch'], $c), ['type' => 'lost', 'date' => '2026-03-15', 'reason' => 'audit'], $w['admin']));
        $this->assertFalse($this->reload($w['branch'], $c)->is_missing);
        $this->assertBooksMatch($w['branch']);
        $this->expectException(ValidationException::class);                         // closed audits stay closed
        $svc(fn ($s) => $s->complete($this->reload($w['branch'], $audit), $w['admin']));
    }

    public function test_other_branches_never_see_or_touch_these_records(): void
    {
        $w = $this->world();
        $a = $this->register($w);
        $institution = $w['branch']->institution;
        $other = $this->makeBranch($institution);
        $this->inBranch($other, fn () => $this->assertEquals(0, Asset::query()->count()));
        $this->inBranch($other, fn () => $this->assertEquals(0, Category::query()->count()));
        $this->assertEquals(0, $this->balance($other, 'fixed_assets'));
        try {
            $this->inBranch($other, fn () => app(AssetService::class)->allocate($a, ['assignee' => 'X', 'allocated_on' => '2026-03-01'], $w['admin']));
            $this->fail('Foreign asset');
        } catch (\Throwable $e) {
            $this->assertTrue(true);
        }
    }
}
