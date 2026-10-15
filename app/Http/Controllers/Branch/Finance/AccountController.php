<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\JournalLine;
use App\Services\Finance\ChartOfAccounts;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Chart of accounts with current balances; cash, bank and mobile-banking accounts. */
class AccountController extends Controller
{
    public function __construct(protected ChartOfAccounts $chart) {}

    public function index(Request $request): View
    {
        $this->authorize('accounting.view');
        $this->chart->ensure();

        $accounts = Account::query()->orderBy('code')->get();
        $sums = JournalLine::query()->selectRaw('account_id, SUM(debit) as d, SUM(credit) as c')->groupBy('account_id')->toBase()->get()->keyBy('account_id');
        $balances = $accounts->mapWithKeys(function (Account $a) use ($sums) {
            $net = (float) ($sums[$a->id]->d ?? 0) - (float) ($sums[$a->id]->c ?? 0);

            return [$a->id => round($a->isDebitNormal() ? $net : -$net, 2)];
        });
        // Group totals = sum of their children (one level of nesting is enough for the default chart; deeper trees roll up recursively).
        $total = function (Account $a) use (&$total, $accounts, $balances) {
            return $a->is_group ? $accounts->where('parent_id', $a->id)->sum(fn ($c) => $total($c)) : $balances[$a->id];
        };

        return view('branch.finance.accounts.index', [
            'accounts' => $accounts,
            'roots' => $accounts->whereNull('parent_id')->values(),
            'balance' => fn (Account $a) => round($total($a), 2),
            'editing' => $request->integer('edit') ? $accounts->firstWhere('id', $request->integer('edit')) : null,
            'groups' => $accounts->where('is_group', true)->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('accounting.post');
        Account::query()->create($this->validated($request));

        return redirect()->route('branch.finance.accounts.index')->with('success', __('Account saved.'));
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('accounting.post');
        $data = $this->validated($request, $account);
        if ($account->lines()->exists() && $data['type'] !== $account->type) {
            return back()->withErrors(['type' => __('The type of an account with postings cannot be changed.')])->withInput();
        }
        if ($account->system_key) {
            unset($data['type'], $data['is_group'], $data['kind']); // system accounts keep their role
        }
        $account->update($data);

        return redirect()->route('branch.finance.accounts.index')->with('success', __('Account saved.'));
    }

    protected function validated(Request $request, ?Account $account = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/', TenantRule::unique('accounts', 'code', $account?->id)],
            'name' => ['required', 'string', 'max:120'],
            'name_bn' => ['nullable', 'string', 'max:120'],
            'type' => ['required', Rule::in(Account::TYPES)],
            'parent_id' => ['nullable', 'integer', TenantRule::exists('accounts')->where('is_group', true)],
            'is_group' => ['nullable', 'boolean'],
            'kind' => ['nullable', Rule::in(Account::KINDS)],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $data['is_group'] = $request->boolean('is_group');
        $data['is_active'] = $request->boolean('is_active', true);
        if ($data['is_group'] || $data['type'] !== 'asset') {
            $data['kind'] = null; // only asset ledgers hold money
        }
        if ($account && (int) ($data['parent_id'] ?? 0) === $account->id) {
            $data['parent_id'] = null;
        }

        return $data;
    }
}
