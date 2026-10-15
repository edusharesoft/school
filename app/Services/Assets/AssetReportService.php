<?php

namespace App\Services\Assets;

use App\Models\Assets\Allocation;
use App\Models\Assets\Asset;
use App\Models\Assets\Category;
use App\Models\Assets\Depreciation;
use App\Models\Assets\Disposal;
use App\Models\Assets\MaintenanceLog;
use App\Models\Assets\MaintenancePlan;
use App\Models\Branch;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Asset figures for the home screen, the reports and the institution-wide (central) view.
 * Branch methods run inside a branch context (BranchScope); consolidated() takes branch ids explicitly.
 */
class AssetReportService
{
    public function __construct(protected LedgerService $ledger, protected ChartOfAccounts $chart) {}

    /** Figures for the asset home screen. */
    public function overview(): array
    {
        $today = CarbonImmutable::today();
        $live = Asset::query()->live()->selectRaw('COUNT(*) as n, COALESCE(SUM(cost),0) as cost, COALESCE(SUM(accumulated_depreciation),0) as accumulated')->toBase()->first();
        $plans = MaintenancePlan::query()->open();

        return [
            'count' => (int) $live->n,
            'cost' => round((float) $live->cost, 2),
            'accumulated' => round((float) $live->accumulated, 2),
            'book' => round((float) $live->cost - (float) $live->accumulated, 2),
            'inUse' => Asset::query()->where('status', 'in_use')->count(),
            'underRepair' => Asset::query()->live()->where('is_under_repair', true)->count(),
            'missing' => Asset::query()->live()->where('is_missing', true)->count(),
            'overdue' => (clone $plans)->whereDate('next_due_on', '<', $today->toDateString())->count(),
            'dueSoon' => (clone $plans)->whereDate('next_due_on', '>=', $today->toDateString())->whereDate('next_due_on', '<=', $today->addDays(30)->toDateString())->count(),
            'openOrders' => MaintenanceLog::query()->whereIn('status', MaintenanceLog::OPEN)->count(),
            'warranty' => Asset::query()->live()->whereDate('warranty_expires_on', '>=', $today->toDateString())->whereDate('warranty_expires_on', '<=', $today->addDays(60)->toDateString())->count(),
            'byCategory' => $this->byCategory(),
            'duePlans' => MaintenancePlan::query()->open()->with('asset:id,code,name,name_bn')->whereDate('next_due_on', '<=', $today->addDays(30)->toDateString())->orderBy('next_due_on')->limit(8)->get(),
            'expiring' => Asset::query()->live()->whereDate('warranty_expires_on', '>=', $today->toDateString())->whereDate('warranty_expires_on', '<=', $today->addDays(60)->toDateString())->orderBy('warranty_expires_on')->limit(6)->get(),
            'books' => $this->bookCheck(),
        ];
    }

    /** Does the register agree with the Fixed assets and Accumulated depreciation accounts? */
    public function bookCheck(): array
    {
        $live = Asset::query()->live()->selectRaw('COALESCE(SUM(cost),0) as cost, COALESCE(SUM(accumulated_depreciation),0) as accumulated')->toBase()->first();
        $fixed = $this->ledger->balance($this->chart->get('fixed_assets'));
        $accum = -$this->ledger->balance($this->chart->get('accumulated_depreciation'));

        return ['cost' => round((float) $live->cost, 2), 'fixed' => $fixed, 'accumulated' => round((float) $live->accumulated, 2), 'accountAccumulated' => round($accum, 2),
            'ok' => abs($fixed - (float) $live->cost) < 0.005 && abs($accum - (float) $live->accumulated) < 0.005];
    }

    /** Cost, depreciation and book value of the live assets per category. */
    public function byCategory(): Collection
    {
        return Asset::query()->live()->join('asset_categories', 'asset_categories.id', '=', 'assets.asset_category_id')
            ->selectRaw('asset_categories.id as id, asset_categories.name as category, asset_categories.kind as kind, COUNT(*) as n, SUM(assets.cost) as cost, SUM(assets.accumulated_depreciation) as accumulated')
            ->groupBy('asset_categories.id', 'asset_categories.name', 'asset_categories.kind')->orderBy('asset_categories.name')->toBase()->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'category' => $r->category, 'kind' => $r->kind, 'count' => (int) $r->n, 'cost' => round((float) $r->cost, 2),
                'accumulated' => round((float) $r->accumulated, 2), 'book' => round((float) $r->cost - (float) $r->accumulated, 2)]);
    }

    // ───────────────────────────── register ─────────────────────────────

    /** $filters: q, category, status (in_stock|in_use|disposed|voided|repair|missing|live), room, condition, kind (laboratory), warranty (expiring) */
    public function registerQuery(array $filters): Builder
    {
        $status = $filters['status'] ?? 'live';

        return Asset::query()->with(['category:id,name,kind', 'room:id,name', 'currentAllocation.employee:id,name', 'currentAllocation.department:id,name', 'currentAllocation.room:id,name'])
            ->search($filters['q'] ?? null)
            ->when(! empty($filters['category']), fn ($q) => $q->where('asset_category_id', (int) $filters['category']))
            ->when(! empty($filters['room']), fn ($q) => $q->where('room_id', (int) $filters['room']))
            ->when(! empty($filters['condition']), fn ($q) => $q->where('condition', $filters['condition']))
            ->when(($filters['kind'] ?? '') === 'laboratory', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('kind', 'laboratory')))
            ->when(! empty($filters['warranty']), fn ($q) => $q->live()->whereDate('warranty_expires_on', '>=', now()->toDateString())->whereDate('warranty_expires_on', '<=', now()->addDays(60)->toDateString()))
            ->when($status === 'live', fn ($q) => $q->live())
            ->when(in_array($status, ['in_stock', 'in_use', 'disposed', 'voided'], true), fn ($q) => $q->where('status', $status))
            ->when($status === 'repair', fn ($q) => $q->live()->where('is_under_repair', true))
            ->when($status === 'missing', fn ($q) => $q->live()->where('is_missing', true));
    }

    public function register(array $filters, int $perPage = 40): LengthAwarePaginator
    {
        return $this->registerQuery($filters)->orderBy('code')->paginate($perPage)->withQueryString();
    }

    public function registerTotals(array $filters): array
    {
        $row = $this->registerQuery($filters)->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(assets.cost),0) as cost, COALESCE(SUM(assets.accumulated_depreciation),0) as accumulated')->first();

        return ['count' => (int) $row->n, 'cost' => round((float) $row->cost, 2), 'accumulated' => round((float) $row->accumulated, 2), 'book' => round((float) $row->cost - (float) $row->accumulated, 2)];
    }

    // ───────────────────────────── depreciation ─────────────────────────────

    /**
     * Depreciation charged between two months (inclusive, YYYY-MM): per category and per month, from the posted rows.
     *
     * @return array{byCategory:Collection, byMonth:Collection, total:float}
     */
    public function depreciation(string $from, string $to): array
    {
        $rows = Depreciation::query()->where('is_reversed', false)->where('period', '>=', $from)->where('period', '<=', $to)
            ->join('assets', 'assets.id', '=', 'asset_depreciations.asset_id')->join('asset_categories', 'asset_categories.id', '=', 'assets.asset_category_id');
        $byCategory = (clone $rows)->selectRaw('asset_categories.name as category, COUNT(DISTINCT asset_depreciations.asset_id) as assets, SUM(asset_depreciations.amount) as amount')
            ->groupBy('asset_categories.id', 'asset_categories.name')->orderBy('asset_categories.name')->toBase()->get()
            ->map(fn ($r) => ['category' => $r->category, 'assets' => (int) $r->assets, 'amount' => round((float) $r->amount, 2)]);
        $byMonth = (clone $rows)->selectRaw('asset_depreciations.period as period, COUNT(*) as assets, SUM(asset_depreciations.amount) as amount')
            ->groupBy('asset_depreciations.period')->orderBy('asset_depreciations.period')->toBase()->get()
            ->map(fn ($r) => ['period' => $r->period, 'assets' => (int) $r->assets, 'amount' => round((float) $r->amount, 2)]);

        return ['byCategory' => $byCategory, 'byMonth' => $byMonth, 'total' => round($byMonth->sum('amount'), 2)];
    }

    // ───────────────────────────── maintenance ─────────────────────────────

    /** @return array{plans:Collection, logs:Collection, byKind:Collection, byAsset:Collection, total:float} */
    public function maintenance(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $logs = MaintenanceLog::query()->with('asset:id,code,name,name_bn')->where('asset_maintenance_logs.status', 'completed')
            ->whereDate('asset_maintenance_logs.completed_on', '>=', $from->toDateString())->whereDate('completed_on', '<=', $to->toDateString());

        return [
            'plans' => MaintenancePlan::query()->open()->with(['asset:id,code,name,name_bn', 'employee:id,name'])->orderBy('next_due_on')->get(),
            'logs' => (clone $logs)->orderByDesc('asset_maintenance_logs.completed_on')->get(),
            'byKind' => (clone $logs)->toBase()->selectRaw('kind, COUNT(*) as n, SUM(cost) as cost')->groupBy('kind')->orderBy('kind')->get()
                ->map(fn ($r) => ['kind' => $r->kind, 'count' => (int) $r->n, 'cost' => round((float) $r->cost, 2)]),
            'byAsset' => (clone $logs)->toBase()->join('assets', 'assets.id', '=', 'asset_maintenance_logs.asset_id')
                ->selectRaw('assets.code as code, assets.name as name, COUNT(*) as n, SUM(asset_maintenance_logs.cost) as cost')->groupBy('assets.id', 'assets.code', 'assets.name')
                ->orderByDesc('cost')->limit(10)->get()->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'count' => (int) $r->n, 'cost' => round((float) $r->cost, 2)]),
            'total' => round((float) (clone $logs)->toBase()->sum('cost'), 2),
        ];
    }

    // ───────────────────────────── disposals & allocations ─────────────────────────────

    /** @return array{rows:Collection, proceeds:float, gain:float, loss:float, bookValue:float} */
    public function disposals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = Disposal::query()->with('asset:id,code,name,name_bn')->where('status', 'posted')
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->orderByDesc('date')->orderByDesc('id')->get();

        return ['rows' => $rows, 'proceeds' => round($rows->sum('proceeds'), 2), 'bookValue' => round($rows->sum('book_value'), 2),
            'gain' => round($rows->where('gain_loss', '>', 0)->sum('gain_loss'), 2), 'loss' => round(abs($rows->where('gain_loss', '<', 0)->sum('gain_loss')), 2)];
    }

    /** Open allocations, optionally only overdue ones or those of a holder. */
    public function allocations(array $filters, int $perPage = 40): LengthAwarePaginator
    {
        return Allocation::query()->with(['asset:id,code,name,name_bn,asset_category_id', 'asset.category:id,name', 'employee:id,name', 'department:id,name', 'room:id,name'])
            ->whereNull('returned_on')
            ->when(! empty($filters['employee']), fn ($q) => $q->where('employee_id', (int) $filters['employee']))
            ->when(! empty($filters['department']), fn ($q) => $q->where('department_id', (int) $filters['department']))
            ->when(! empty($filters['overdue']), fn ($q) => $q->whereDate('expected_return_on', '<', now()->toDateString()))
            ->when(trim((string) ($filters['q'] ?? '')), fn ($q, $t) => $q->whereHas('asset', fn ($a) => $a->search($t)))
            ->orderBy('allocated_on')->paginate($perPage)->withQueryString();
    }

    // ───────────────────────────── central ─────────────────────────────

    /** Every branch side by side. */
    public function consolidated(array $branchIds): array
    {
        $branches = Branch::query()->whereIn('id', $branchIds)->ordered()->get(['id', 'name', 'code']);
        $assets = DB::table('assets')->whereIn('branch_id', $branchIds)->whereIn('status', ['in_stock', 'in_use'])
            ->selectRaw('branch_id, COUNT(*) as n, SUM(cost) as cost, SUM(accumulated_depreciation) as accumulated, SUM(CASE WHEN is_under_repair = 1 THEN 1 ELSE 0 END) as repair, SUM(CASE WHEN is_missing = 1 THEN 1 ELSE 0 END) as missing')
            ->groupBy('branch_id')->get()->keyBy('branch_id');
        $overdue = DB::table('asset_maintenance_plans')->join('assets', 'assets.id', '=', 'asset_maintenance_plans.asset_id')->whereIn('asset_maintenance_plans.branch_id', $branchIds)
            ->whereIn('assets.status', ['in_stock', 'in_use'])->where('asset_maintenance_plans.is_active', true)->whereDate('asset_maintenance_plans.next_due_on', '<', now()->toDateString())
            ->selectRaw('asset_maintenance_plans.branch_id as branch_id, COUNT(*) as n')->groupBy('asset_maintenance_plans.branch_id')->pluck('n', 'branch_id');
        $disposed = DB::table('asset_disposals')->whereIn('branch_id', $branchIds)->where('status', 'posted')->whereDate('date', '>=', CarbonImmutable::today()->startOfYear()->toDateString())
            ->selectRaw('branch_id, COUNT(*) as n, SUM(gain_loss) as gl')->groupBy('branch_id')->get()->keyBy('branch_id');

        $rows = $branches->map(fn (Branch $b) => [
            'branch' => $b, 'count' => (int) ($assets[$b->id]->n ?? 0), 'cost' => round((float) ($assets[$b->id]->cost ?? 0), 2), 'accumulated' => round((float) ($assets[$b->id]->accumulated ?? 0), 2),
            'book' => round((float) ($assets[$b->id]->cost ?? 0) - (float) ($assets[$b->id]->accumulated ?? 0), 2), 'repair' => (int) ($assets[$b->id]->repair ?? 0), 'missing' => (int) ($assets[$b->id]->missing ?? 0),
            'overdue' => (int) ($overdue[$b->id] ?? 0), 'disposed' => (int) ($disposed[$b->id]->n ?? 0), 'gainLoss' => round((float) ($disposed[$b->id]->gl ?? 0), 2),
        ]);

        return ['rows' => $rows, 'total' => [
            'count' => $rows->sum('count'), 'cost' => round($rows->sum('cost'), 2), 'accumulated' => round($rows->sum('accumulated'), 2), 'book' => round($rows->sum('book'), 2),
            'repair' => $rows->sum('repair'), 'missing' => $rows->sum('missing'), 'overdue' => $rows->sum('overdue'), 'disposed' => $rows->sum('disposed'), 'gainLoss' => round($rows->sum('gainLoss'), 2),
        ]];
    }
}
