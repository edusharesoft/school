<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Finance\Account;
use App\Models\Finance\Vendor;
use App\Models\Institution;
use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ProcurementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo inventory for every demo branch (local/staging only): categories, items with opening stock, two suppliers, a few
 * purchases (one on credit, one paid), an approved purchase order waiting for its goods, a pending purchase request and
 * some issues — all through the same services as the UI, so stock and the books stay in step.
 * Idempotent: a branch that already has items is skipped.
 */
class DemoInventorySeeder extends Seeder
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
                if (! $admin || Item::query()->exists()) {
                    return;
                }
                $buyer = User::query()->where('email', "accounts.branch{$branch->sort_order}@demo.test")->first() ?? $admin;
                DB::transaction(fn () => $this->seedBranch($admin, $buyer));
            });
        }
    }

    protected function seedBranch(User $admin, User $buyer): void
    {
        $chart = app(ChartOfAccounts::class);
        $chart->ensure();
        $inventory = app(InventoryService::class);
        $buying = app(ProcurementService::class);
        $today = CarbonImmutable::today();

        $stationery = Category::query()->create(['name' => 'Stationery', 'kind' => 'general', 'is_active' => true]);
        $lab = Category::query()->create(['name' => 'Laboratory', 'kind' => 'laboratory', 'is_active' => true]);
        $cleaning = Category::query()->create(['name' => 'Cleaning supplies', 'kind' => 'general', 'is_active' => true]);

        $items = [];
        foreach ([
            ['A4 paper', 'কাগজ (A4)', $stationery, 'consumable', 'ream', 20, 40, 480],
            ['Whiteboard marker', 'হোয়াইটবোর্ড মার্কার', $stationery, 'consumable', 'pcs', 30, 120, 35],
            ['Chalk box', 'চকের বাক্স', $stationery, 'consumable', 'box', 10, 25, 60],
            ['Hydrochloric acid', 'হাইড্রোক্লোরিক অ্যাসিড', $lab, 'consumable', 'litre', 5, 12, 650],
            ['Test tube', 'টেস্ট টিউব', $lab, 'consumable', 'pcs', 50, 200, 18],
            ['Microscope', 'অণুবীক্ষণ যন্ত্র', $lab, 'durable', 'pcs', 0, 0, 0],
            ['Floor cleaner', 'ফ্লোর ক্লিনার', $cleaning, 'consumable', 'litre', 10, 8, 150],
        ] as [$name, $bn, $category, $type, $unit, $reorder, $qty, $cost]) {
            $items[$name] = $inventory->createItem(['name' => $name, 'name_bn' => $bn, 'inventory_category_id' => $category->id, 'item_type' => $type, 'unit' => $unit,
                'reorder_level' => $reorder, 'is_active' => true]);
            if ($qty > 0) {
                $inventory->adjust(['date' => $today->subMonths(2)->toDateString(), 'reason' => 'opening', 'note' => __('Opening stock of :item', ['item' => $name])],
                    [['inventory_item_id' => $items[$name]->id, 'quantity' => $qty, 'unit_cost' => $cost]], $admin);
            }
        }

        $rahim = Vendor::query()->create(['name' => 'Rahim Stationers', 'phone' => '01711000001', 'address' => 'Bangshal, Dhaka', 'is_active' => true]);
        $science = Vendor::query()->create(['name' => 'Bangla Science House', 'phone' => '01711000002', 'address' => 'Nilkhet, Dhaka', 'is_active' => true]);
        $cash = Account::query()->where('system_key', 'cash')->first();
        $bank = Account::query()->where('system_key', 'bank')->first();

        // a credit purchase, partly paid
        $grn = $buying->receive(['vendor_id' => $rahim->id, 'received_on' => $today->subDays(20)->toDateString(), 'bill_no' => 'RS-1042', 'payment_mode' => 'credit'], [
            ['inventory_item_id' => $items['A4 paper']->id, 'quantity' => 20, 'unit_cost' => 490],
            ['inventory_item_id' => $items['Whiteboard marker']->id, 'quantity' => 60, 'unit_cost' => 36],
        ], $buyer);
        if ($bank) {
            $buying->paySupplier($grn, 5000, $bank, $today->subDays(12)->toDateString(), 'CHQ-2231', $admin);
        }
        // a purchase paid at once
        if ($cash) {
            $buying->receive(['vendor_id' => $science->id, 'received_on' => $today->subDays(9)->toDateString(), 'payment_mode' => 'paid', 'paid_from_account_id' => $cash->id], [
                ['inventory_item_id' => $items['Test tube']->id, 'quantity' => 100, 'unit_cost' => 17],
            ], $buyer);
        }

        // an approved order that has not arrived yet, and a request waiting for approval
        $request = $buying->createRequisition(['date' => $today->subDays(6)->toDateString(), 'purpose' => 'Chemistry practical — new batch', 'needed_by' => $today->addDays(10)->toDateString()], [
            ['inventory_item_id' => $items['Hydrochloric acid']->id, 'quantity' => 10, 'estimated_unit_cost' => 650],
            ['inventory_item_id' => $items['Microscope']->id, 'quantity' => 2, 'estimated_unit_cost' => 14000],
        ], $buyer);
        $buying->approveRequisition($request, $admin);
        $order = $buying->createOrder(['vendor_id' => $science->id, 'order_date' => $today->subDays(4)->toDateString(), 'expected_on' => $today->addDays(5)->toDateString()], [
            ['inventory_item_id' => $items['Hydrochloric acid']->id, 'quantity' => 10, 'unit_cost' => 640],
            ['inventory_item_id' => $items['Microscope']->id, 'quantity' => 2, 'unit_cost' => 13500],
        ], $buyer, $request);
        $buying->approveOrder($order, $admin);
        $buying->createRequisition(['date' => $today->subDays(1)->toDateString(), 'purpose' => 'Term-end cleaning'], [
            ['inventory_item_id' => $items['Floor cleaner']->id, 'quantity' => 20, 'estimated_unit_cost' => 150],
        ], $buyer);

        // some consumption
        $inventory->issue(['date' => $today->subDays(5)->toDateString(), 'recipient' => 'Exam office', 'purpose' => 'Mid-term question papers'], [['inventory_item_id' => $items['A4 paper']->id, 'quantity' => 12]], $buyer);
        $inventory->issue(['date' => $today->subDays(3)->toDateString(), 'recipient' => 'Chemistry lab', 'purpose' => 'Practical class'], [
            ['inventory_item_id' => $items['Hydrochloric acid']->id, 'quantity' => 9], ['inventory_item_id' => $items['Test tube']->id, 'quantity' => 40],
        ], $buyer);
        $inventory->issue(['date' => $today->subDays(2)->toDateString(), 'recipient' => 'Class teachers'], [['inventory_item_id' => $items['Chalk box']->id, 'quantity' => 18]], $buyer);   // leaves 7 — below its reorder level
    }
}
