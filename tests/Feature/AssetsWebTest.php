<?php

namespace Tests\Feature;

use App\Models\Assets\Allocation;
use App\Models\Assets\Asset;
use App\Models\Assets\Audit;
use App\Models\Assets\Category;
use App\Models\Assets\Disposal;
use App\Models\Assets\MaintenanceLog;
use App\Models\Assets\MaintenancePlan;
use App\Models\Branch;
use App\Models\Finance\Vendor;
use App\Models\Inventory\Category as StockCategory;
use App\Models\Inventory\Item;
use App\Models\User;
use App\Services\Assets\AssetService;
use App\Services\Assets\DepreciationService;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ProcurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Asset register screens, forms and permissions. */
class AssetsWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
    }

    protected function url(Branch $branch, string $path = ''): string
    {
        return $this->branchUrl($branch, 'assets'.($path === '' ? '' : '/'.$path));
    }

    protected function world(): array
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');
        $cat = $this->inBranch($branch, fn () => Category::query()->create(['name' => 'Furniture', 'kind' => 'general', 'depreciation_method' => 'straight_line', 'useful_life_months' => 120, 'salvage_percent' => 0, 'is_active' => true]));
        $cash = $this->inBranch($branch, fn () => app(ChartOfAccounts::class)->id('cash'));

        return compact('branch', 'admin', 'cat', 'cash');
    }

    protected function asset(array $w, array $over = []): Asset
    {
        return $this->inBranch($w['branch'], fn () => app(AssetService::class)->register($over + [
            'name' => 'Teacher table', 'asset_category_id' => $w['cat']->id, 'acquisition_type' => 'purchase', 'acquired_on' => '2026-01-10', 'in_service_on' => '2026-01-10',
            'cost' => 12000, 'paid_from_account_id' => $w['cash'], 'condition' => 'new',
        ], $w['admin']));
    }

    protected function as(array $w, ?User $user = null): static
    {
        return $this->actingOnBranch($user ?? $w['admin'], $w['branch']);
    }

    public function test_every_screen_renders_for_the_branch_admin(): void
    {
        $w = $this->world();
        $a = $this->asset($w);
        $this->as($w)->post($this->url($w['branch'], "register/{$a->id}/allocate"), ['allocated_on' => '2026-03-01', 'assignee' => 'Mr. Karim'])->assertSessionHasNoErrors();
        $this->as($w)->post($this->url($w['branch'], "register/{$a->id}/plans"), ['title' => 'Annual service', 'kind' => 'preventive', 'interval_months' => 12, 'start_on' => '2026-03-20'])->assertSessionHasNoErrors();
        $this->as($w)->post($this->url($w['branch'], "register/{$a->id}/orders"), ['kind' => 'repair', 'title' => 'Broken leg', 'reported_on' => '2026-03-14'])->assertSessionHasNoErrors();
        $this->as($w)->post($this->url($w['branch'], 'depreciation'), ['period' => '2026-02'])->assertSessionHasNoErrors();
        $this->as($w)->post($this->url($w['branch'], 'audits'), ['title' => 'Check', 'date' => '2026-03-15'])->assertSessionHasNoErrors();

        $run = $this->inBranch($w['branch'], fn () => \App\Models\Assets\DepreciationRun::query()->firstOrFail());
        $log = $this->inBranch($w['branch'], fn () => MaintenanceLog::query()->firstOrFail());
        $audit = $this->inBranch($w['branch'], fn () => Audit::query()->firstOrFail());

        foreach ([
            '', 'categories', 'register', 'register/create', 'register/from-stock', "register/{$a->id}", "register/{$a->id}/edit", "register/labels?ids={$a->id}", 'register/export',
            'allocations', 'maintenance', 'maintenance/plans', "maintenance/orders/{$log->id}", 'depreciation', "depreciation/{$run->id}", "register/{$a->id}/dispose",
            'disposals', "audits", "audits/{$audit->id}", 'reports/summary', 'reports/depreciation', 'reports/maintenance',
        ] as $path) {
            $this->as($w)->get($this->url($w['branch'], $path))->assertOk();
        }
        $this->as($w)->get($this->url($w['branch'], 'register?q=table&status=in_use'))->assertOk()->assertSee('Teacher table');
        $csv = $this->as($w)->get($this->url($w['branch'], 'register/export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('AS-000001,"Teacher table",Furniture', $csv);
        $this->assertStringContainsString('12000.00,200.00,11800.00', $csv);
    }

    public function test_registering_several_assets_and_the_validation(): void
    {
        $w = $this->world();
        $this->as($w)->post($this->url($w['branch'], 'register'), [
            'name' => 'Bench', 'asset_category_id' => $w['cat']->id, 'acquisition_type' => 'purchase', 'acquired_on' => '2026-03-01', 'cost' => 3000, 'condition' => 'new', 'quantity' => 3,
        ])->assertSessionHasErrors('paid_from_account_id');
        $this->as($w)->post($this->url($w['branch'], 'register'), [
            'name' => 'Bench', 'asset_category_id' => $w['cat']->id, 'acquisition_type' => 'purchase', 'acquired_on' => '2026-03-01', 'cost' => 3000, 'condition' => 'new', 'quantity' => 3,
            'paid_from_account_id' => $w['cash'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(3, $this->inBranch($w['branch'], fn () => Asset::query()->count()));
        $this->as($w)->post($this->url($w['branch'], 'register'), [
            'name' => 'X', 'asset_category_id' => 99999, 'acquisition_type' => 'gift', 'acquired_on' => '2099-01-01', 'cost' => 0, 'condition' => 'new',
        ])->assertSessionHasErrors(['asset_category_id', 'acquisition_type', 'acquired_on', 'cost']);
    }

    public function test_depreciation_run_dispose_and_reversal_through_the_screens(): void
    {
        $w = $this->world();
        $a = $this->asset($w);
        $this->as($w)->post($this->url($w['branch'], 'depreciation'), ['period' => 'nonsense'])->assertSessionHasErrors('period');
        $this->as($w)->post($this->url($w['branch'], 'depreciation'), ['period' => '2026-02'])->assertSessionHasNoErrors();
        $this->assertGreaterThan(0, (float) $this->inBranch($w['branch'], fn () => $a->fresh()->accumulated_depreciation));
        $run = $this->inBranch($w['branch'], fn () => \App\Models\Assets\DepreciationRun::query()->firstOrFail());
        $this->as($w)->post($this->url($w['branch'], "depreciation/{$run->id}/reverse"), [])->assertSessionHasErrors('reason');
        $this->as($w)->post($this->url($w['branch'], "depreciation/{$run->id}/reverse"), ['reason' => 'wrong month'])->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->inBranch($w['branch'], fn () => (float) $a->fresh()->accumulated_depreciation));

        $this->as($w)->post($this->url($w['branch'], "register/{$a->id}/dispose"), ['type' => 'sale', 'date' => '2026-03-15', 'proceeds' => 9000, 'account_id' => $w['cash'], 'buyer' => 'Scrap dealer'])->assertSessionHasNoErrors();
        $disposal = $this->inBranch($w['branch'], fn () => Disposal::query()->firstOrFail());
        $this->as($w)->get($this->url($w['branch'], "disposals/{$disposal->id}"))->assertOk()->assertSee($disposal->disposal_no);
        $this->as($w)->post($this->url($w['branch'], "disposals/{$disposal->id}/reverse"), ['reason' => 'mistake'])->assertSessionHasNoErrors();
        $this->assertTrue($this->inBranch($w['branch'], fn () => $a->fresh()->isLive()));
    }

    public function test_allocation_return_audit_results_and_stock_transfer(): void
    {
        $w = $this->world();
        $a = $this->asset($w);
        $this->as($w)->post($this->url($w['branch'], "register/{$a->id}/allocate"), ['allocated_on' => '2026-03-01'])->assertSessionHasErrors();   // a holder is required
        $this->as($w)->post($this->url($w['branch'], "register/{$a->id}/allocate"), ['allocated_on' => '2026-03-01', 'assignee' => 'Mr. Karim'])->assertSessionHasNoErrors();
        $alloc = $this->inBranch($w['branch'], fn () => Allocation::query()->firstOrFail());
        $this->as($w)->get($this->url($w['branch'], 'allocations'))->assertOk()->assertSee('Mr. Karim');
        $this->as($w)->post($this->url($w['branch'], "allocations/{$alloc->id}/return"), ['returned_on' => '2026-03-10', 'return_condition' => 'good'])->assertSessionHasNoErrors();

        $this->as($w)->post($this->url($w['branch'], 'audits'), ['title' => 'Count', 'date' => '2026-03-15'])->assertSessionHasNoErrors();
        $audit = $this->inBranch($w['branch'], fn () => Audit::query()->firstOrFail());
        $line = $this->inBranch($w['branch'], fn () => $audit->items()->firstOrFail());
        $this->as($w)->put($this->url($w['branch'], "audits/{$audit->id}/results"), ['complete' => 1, 'results' => [$line->id => ['result' => 'found', 'found_location' => 'Room 3']]])->assertSessionHasNoErrors();
        $this->assertSame('Room 3', $this->inBranch($w['branch'], fn () => $a->fresh()->location));
        $this->assertSame('completed', $this->inBranch($w['branch'], fn () => $audit->fresh()->status));

        // durable stock → asset
        $stockCat = $this->inBranch($w['branch'], fn () => StockCategory::query()->create(['name' => 'Lab', 'kind' => 'laboratory', 'is_active' => true]));
        $vendor = $this->inBranch($w['branch'], fn () => Vendor::query()->create(['name' => 'Supplier', 'is_active' => true]));
        $scope = $this->inBranch($w['branch'], function () use ($stockCat, $vendor, $w) {
            $item = app(InventoryService::class)->createItem(['name' => 'Microscope', 'inventory_category_id' => $stockCat->id, 'item_type' => 'durable', 'unit' => 'pcs', 'is_active' => true]);
            app(ProcurementService::class)->receive(['vendor_id' => $vendor->id, 'received_on' => '2026-03-01', 'payment_mode' => 'credit'], [['inventory_item_id' => $item->id, 'quantity' => 2, 'unit_cost' => 8000]], $w['admin']);

            return $item;
        });
        $this->as($w)->get($this->url($w['branch'], "register/from-stock?item={$scope->id}"))->assertOk()->assertSee('Microscope');
        $this->as($w)->post($this->url($w['branch'], 'register/from-stock'), [
            'inventory_item_id' => $scope->id, 'quantity' => 2, 'asset_category_id' => $w['cat']->id, 'acquired_on' => '2026-03-05', 'condition' => 'new',
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->inBranch($w['branch'], fn () => Asset::query()->count()));
    }

    public function test_permissions_lab_manager_and_store_keeper(): void
    {
        $w = $this->world();
        $a = $this->asset($w);
        $lab = $this->branchUser($w['branch'], 'lab-manager');
        $store = $this->branchUser($w['branch'], 'store-keeper');

        $this->as($w, $lab)->get($this->url($w['branch'], 'register'))->assertOk();
        $this->as($w, $lab)->get($this->url($w['branch'], 'register/create'))->assertOk();
        $this->as($w, $lab)->post($this->url($w['branch'], 'depreciation'), ['period' => '2026-02'])->assertForbidden();
        $this->as($w, $lab)->post($this->url($w['branch'], "register/{$a->id}/dispose"), ['type' => 'scrap', 'date' => '2026-03-15'])->assertForbidden();
        $this->as($w, $lab)->post($this->url($w['branch'], "register/{$a->id}/void"), ['reason' => 'x'])->assertForbidden();

        $this->as($w, $store)->get($this->url($w['branch'], 'register'))->assertOk();
        $this->as($w, $store)->get($this->url($w['branch'], 'register/create'))->assertForbidden();
        $this->as($w, $store)->post($this->url($w['branch'], "register/{$a->id}/allocate"), ['allocated_on' => '2026-03-01', 'assignee' => 'X'])->assertForbidden();

        $teacher = $this->branchUser($w['branch'], 'teacher');
        $this->as($w, $teacher)->get($this->url($w['branch'], ''))->assertForbidden();

        $this->as($w)->post($this->url($w['branch'], "register/{$a->id}/void"), ['reason' => 'entered twice'])->assertSessionHasNoErrors();
        $this->assertSame('voided', $this->inBranch($w['branch'], fn () => $a->fresh()->status));
    }

    public function test_other_branch_records_are_not_reachable(): void
    {
        $w = $this->world();
        $a = $this->asset($w);
        $other = $this->makeBranch($w['branch']->institution);
        $otherAdmin = $this->branchUser($other, 'branch-admin');
        $this->actingOnBranch($otherAdmin, $other)->get($this->url($other, "register/{$a->id}"))->assertNotFound();
        $this->actingOnBranch($otherAdmin, $other)->post($this->url($other, "register/{$a->id}/void"), ['reason' => 'x'])->assertNotFound();
        $this->actingOnBranch($otherAdmin, $other)->get($this->url($other, 'register'))->assertOk()->assertDontSee('Teacher table');
    }

    public function test_central_consolidated_assets_page(): void
    {
        $admin = User::factory()->role('institution-admin')->create();
        $north = $this->makeBranch($admin->institution, ['name' => 'North Campus']);
        $this->inBranch($north, fn () => app(ChartOfAccounts::class)->ensure());
        $this->actingCentral($admin)->get($this->centralUrl('super-admin/assets'))->assertOk()->assertSee('North Campus');
    }
}
