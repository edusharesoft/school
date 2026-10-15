<?php

namespace App\Http\Controllers\Branch\Assets;

use App\Http\Controllers\Controller;
use App\Models\Assets\DepreciationRun;
use App\Services\Assets\DepreciationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Preview and post monthly depreciation; look at and reverse runs. Posting needs assets.approve. */
class DepreciationController extends Controller
{
    public function __construct(protected DepreciationService $depreciation) {}

    public function index(Request $request): View
    {
        $this->authorize('assets.view');
        $period = $request->input('period', now()->format('Y-m'));
        $preview = null;
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $period) && $period <= now()->format('Y-m')) {
            $preview = $this->depreciation->preview($period);
        } else {
            $period = now()->format('Y-m');
            $preview = $this->depreciation->preview($period);
        }

        return view('branch.assets.depreciation.index', [
            'period' => $period, 'preview' => $preview,
            'runs' => DepreciationRun::query()->with('runner:id,name')->latest('id')->paginate(20),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $this->authorize('assets.approve');
        $data = $request->validate(['period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']]);
        $run = $this->depreciation->run($data['period'], $request->user());

        return redirect()->route('branch.assets.depreciation.show', $run)->with('success', __('Depreciation :no posted: ৳:total for :n assets.', ['no' => $run->run_no, 'total' => number_format($run->total, 2), 'n' => $run->asset_count]));
    }

    public function show(DepreciationRun $run): View
    {
        $this->authorize('assets.view');

        return view('branch.assets.depreciation.show', ['run' => $run->load('runner'), 'rows' => $run->rows()->with('asset:id,code,name,name_bn')->orderBy('period')->orderBy('asset_id')->get(),
            'entries' => app(\App\Services\Finance\LedgerService::class)->entriesFor($run)]);
    }

    public function reverse(Request $request, DepreciationRun $run): RedirectResponse
    {
        $this->authorize('assets.approve');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->depreciation->reverse($run, $data['reason'], $request->user());

        return redirect()->route('branch.assets.depreciation.index')->with('success', __('Depreciation run reversed.'));
    }
}
