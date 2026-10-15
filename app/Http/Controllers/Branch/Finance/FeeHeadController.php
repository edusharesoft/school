<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\FeeHead;
use App\Services\Finance\ChartOfAccounts;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Fee heads (admission, tuition, session, exam …) and the income account each is credited to. */
class FeeHeadController extends Controller
{
    public function __construct(protected ChartOfAccounts $chart) {}

    public function index(Request $request): View
    {
        $this->authorize('fees.view');
        $this->chart->ensure();

        return view('branch.finance.heads.index', [
            'heads' => FeeHead::query()->with('incomeAccount:id,code,name')->ordered()->get(),
            'editing' => $request->integer('edit') ? FeeHead::query()->find($request->integer('edit')) : null,
            'incomeAccounts' => Account::query()->postable()->where('type', 'income')->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('fees.create');
        $data = $this->validated($request);
        $data['income_account_id'] ??= $this->chart->incomeAccountForHead($data['name'], $data['name_bn'] ?? null)->id;
        FeeHead::query()->create($data);

        return redirect()->route('branch.finance.heads.index')->with('success', __('Fee head saved.'));
    }

    public function update(Request $request, FeeHead $head): RedirectResponse
    {
        $this->authorize('fees.update');
        $data = $this->validated($request, $head);
        $data['income_account_id'] ??= $head->income_account_id ?? $this->chart->incomeAccountForHead($data['name'], $data['name_bn'] ?? null)->id;
        $head->update($data);

        return redirect()->route('branch.finance.heads.index')->with('success', __('Fee head saved.'));
    }

    protected function validated(Request $request, ?FeeHead $head = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', TenantRule::unique('fee_heads', 'code', $head?->id)],
            'category' => ['required', Rule::in(FeeHead::CATEGORIES)],
            'income_account_id' => ['nullable', 'integer', TenantRule::exists('accounts')->where('type', 'income')->where('is_group', false)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return ['code' => strtoupper($data['code']), 'sort_order' => (int) ($data['sort_order'] ?? 0), 'is_active' => $request->boolean('is_active', true)] + $data;
    }
}
