<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\FeePayment;
use App\Models\Finance\FeeRefund;
use App\Models\Students\Student;
use App\Services\Finance\FeePaymentService;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\LedgerService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Money receipts: collect, print, list, reverse; refunds of advance credit. */
class FeePaymentController extends Controller
{
    public function __construct(protected FeePaymentService $payments) {}

    public function index(Request $request): View
    {
        $this->authorize('payments.view');
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        $query = FeePayment::query()->with(['student:id,name,student_code', 'account:id,code,name', 'receiver:id,name'])
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->when($request->input('method'), fn ($q, $m) => $q->where('method', $m))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('receipt_no', 'like', "%$t%")->orWhere('reference', 'like', "%$t%")
                ->orWhereHas('student', fn ($s) => $s->search($t))));

        return view('branch.finance.payments.index', [
            'payments' => (clone $query)->latest('date')->latest('id')->paginate(30)->withQueryString(),
            'total' => (float) (clone $query)->where('status', 'completed')->sum('amount'),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('payments.collect');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'method' => ['required', Rule::in(FeePayment::METHODS)],
            'account_id' => ['nullable', 'integer', TenantRule::exists('accounts')],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn () => in_array($request->input('method'), ['cheque', 'bkash', 'nagad', 'rocket', 'bank', 'card'], true))],
            'notes' => ['nullable', 'string', 'max:255'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer'],
        ]);
        $account = ($data['account_id'] ?? null) ? Account::query()->findOrFail($data['account_id']) : null;

        $payment = $this->payments->collect($student, (float) $data['amount'], $data['method'], $account, $data['date'],
            $data['reference'] ?? null, $data['notes'] ?? null, $data['invoice_ids'] ?? [], $request->user());

        return redirect()->route('branch.finance.students.show', [$student, 'tab' => 'payments'])
            ->with('success', __('Receipt :no saved.', ['no' => $payment->receipt_no]))->with('print_receipt', $payment->id);
    }

    public function show(Request $request, FeePayment $payment): View
    {
        abort_unless($request->user()->can('payments.view') || $request->user()->can('payments.collect'), 403);
        $payment->load(['student', 'allocations.invoice', 'account', 'receiver:id,name']);

        return view('branch.finance.payments.receipt', [
            'payment' => $payment, 'branch' => tenant()->branch(),
            'balance' => $this->payments->outstanding($payment->student),
            'credit' => $this->payments->credit($payment->student),
            'note' => (string) app(FinanceSettings::class)->get('receipt_note'),
            'entries' => app(LedgerService::class)->entriesFor($payment),
        ]);
    }

    public function reverse(Request $request, FeePayment $payment): RedirectResponse
    {
        $this->authorize('payments.refund');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->payments->reverse($payment, $data['reason'], $request->user());

        return back()->with('success', __('Receipt :no reversed.', ['no' => $payment->receipt_no]));
    }

    public function refund(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('payments.refund');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(FeePayment::METHODS)],
            'account_id' => ['nullable', 'integer', TenantRule::exists('accounts')],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $account = ($data['account_id'] ?? null) ? Account::query()->findOrFail($data['account_id']) : null;
        $refund = $this->payments->refund($student, (float) $data['amount'], $data['method'], $account, $data['reason'], $data['date'], $request->user(), $data['reference'] ?? null);

        return redirect()->route('branch.finance.students.show', [$student, 'tab' => 'payments'])->with('success', __('Refund :no recorded.', ['no' => $refund->refund_no]));
    }

    public function refundShow(Request $request, FeeRefund $refund): View
    {
        $this->authorize('payments.view');

        return view('branch.finance.payments.refund', ['refund' => $refund->load(['student', 'account', 'refunder:id,name']), 'branch' => tenant()->branch()]);
    }
}
