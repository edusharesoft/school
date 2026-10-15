<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Finance\Vendor;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\ExpenseService;
use App\Services\Finance\LedgerService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Expense vouchers: request, approve / reject, pay (credit purchases), reverse; bill attachments. */
class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenses) {}

    public function index(Request $request): View
    {
        $this->authorize('expenses.view');
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        $query = Expense::query()->with(['account:id,code,name', 'vendor:id,name', 'requester:id,name'])
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->integer('account_id'), fn ($q, $id) => $q->where('account_id', $id))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('expense_no', 'like', "%$t%")->orWhere('description', 'like', "%$t%")));

        return view('branch.finance.expenses.index', [
            'expenses' => (clone $query)->latest('date')->latest('id')->paginate(30)->withQueryString(),
            'total' => (float) (clone $query)->whereIn('status', ['approved', 'paid'])->sum('amount'),
            'pending' => Expense::query()->where('status', 'pending')->count(),
            'accounts' => $this->expenseAccounts(),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function create(): View
    {
        $this->authorize('expenses.create');

        return view('branch.finance.expenses.create', [
            'accounts' => $this->expenseAccounts(),
            'moneyAccounts' => Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
            'vendors' => Vendor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('expenses.create');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'account_id' => ['required', 'integer', TenantRule::exists('accounts')],
            'vendor_id' => ['nullable', 'integer', TenantRule::exists('vendors')],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'payment_mode' => ['required', Rule::in(['paid', 'credit'])],
            'paid_from_account_id' => ['nullable', 'required_if:payment_mode,paid', 'integer', TenantRule::exists('accounts')],
            'reference' => ['nullable', 'string', 'max:100'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $expense = $this->expenses->create($data, $request->file('attachment'), $request->user());

        return redirect()->route('branch.finance.expenses.show', $expense)->with('success', __('Expense :no submitted for approval.', ['no' => $expense->expense_no]));
    }

    public function show(Expense $expense): View
    {
        $this->authorize('expenses.view');
        $expense->load(['account', 'paidFrom', 'vendor', 'requester:id,name', 'approver:id,name']);

        return view('branch.finance.expenses.show', [
            'expense' => $expense,
            'entries' => app(LedgerService::class)->entriesFor($expense),
            'moneyAccounts' => Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
        ]);
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('expenses.approve');
        $this->expenses->approve($expense, $request->user(), $request->input('note'));

        return back()->with('success', __('Expense approved and posted.'));
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('expenses.approve');
        $data = $request->validate(['note' => ['required', 'string', 'max:255']]);
        $this->expenses->reject($expense, $data['note'], $request->user());

        return back()->with('success', __('Expense rejected.'));
    }

    public function pay(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('expenses.update');
        $data = $request->validate(['account_id' => ['required', 'integer', TenantRule::exists('accounts')], 'date' => ['required', 'date', 'before_or_equal:today']]);
        $this->expenses->pay($expense, Account::query()->findOrFail($data['account_id']), $data['date'], $request->user());

        return back()->with('success', __('Payment recorded.'));
    }

    public function reverse(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('accounting.reverse');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->expenses->reverse($expense, $data['reason'], $request->user());

        return back()->with('success', __('Expense reversed.'));
    }

    public function attachment(Expense $expense): StreamedResponse
    {
        $this->authorize('expenses.view');
        abort_unless($expense->attachment_path && Storage::disk('local')->exists($expense->attachment_path), 404);

        return Storage::disk('local')->response($expense->attachment_path);
    }

    protected function expenseAccounts()
    {
        app(ChartOfAccounts::class)->ensure();

        return Account::query()->postable()->where('type', 'expense')->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]);
    }
}
