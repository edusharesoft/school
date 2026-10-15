<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Models\Assets\Asset;
use App\Models\Assets\Disposal;
use App\Services\Assets\AssetReportService;
use App\Services\Assets\DepreciationService;
use App\Services\Assets\DisposalService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Selling, scrapping, donating or writing off an asset. Needs assets.approve (it changes the books). */
class DisposalController extends Controller
{
    use AssetLookups;

    public function __construct(protected DisposalService $disposals) {}

    public function index(Request $request, AssetReportService $reports): View
    {
        $this->authorize('assets.view');
        $from = $request->date('from') ? CarbonImmutable::parse($request->date('from')) : CarbonImmutable::today()->startOfYear();
        $to = $request->date('to') ? CarbonImmutable::parse($request->date('to')) : CarbonImmutable::today();

        return view('branch.assets.disposals.index', $reports->disposals($from->startOfDay(), $to->startOfDay()) + ['from' => $from, 'to' => $to]);
    }

    public function create(Asset $asset, DepreciationService $depreciation): View
    {
        $this->authorize('assets.approve');
        abort_unless($asset->isLive(), 404);
        // Show what the book value will be after the catch-up depreciation.
        $catchUp = round(array_sum(array_column($depreciation->plan($asset, DepreciationService::previousPeriod(DepreciationService::periodOf(now()))), 'amount')), 2);

        return view('branch.assets.disposals.create', ['asset' => $asset->load('category'), 'moneyAccounts' => $this->moneyAccounts(), 'catchUp' => $catchUp]);
    }

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('assets.approve');
        $data = $request->validate([
            'type' => ['required', Rule::in(Disposal::TYPES)], 'date' => ['required', 'date', 'before_or_equal:today'], 'proceeds' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'account_id' => ['nullable', 'integer'], 'buyer' => ['nullable', 'string', 'max:150'], 'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $disposal = $this->disposals->dispose($asset, $data, $request->user());

        return redirect()->route('branch.assets.disposals.show', $disposal)->with('success', __('Disposal :no posted.', ['no' => $disposal->disposal_no]));
    }

    public function show(Disposal $disposal): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.disposals.show', ['disposal' => $disposal->load(['asset.category', 'account', 'disposer']),
            'entries' => app(\App\Services\Finance\LedgerService::class)->entriesFor($disposal)]);
    }

    public function reverse(Request $request, Disposal $disposal): RedirectResponse
    {
        $this->authorize('assets.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->disposals->reverse($disposal, $data['reason'], $request->user());

        return redirect()->route('branch.assets.register.show', $disposal->asset_id)->with('success', __('Disposal reversed — the asset is back in the register.'));
    }
}
