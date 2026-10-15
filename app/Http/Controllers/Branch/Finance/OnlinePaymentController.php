<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Payments\OnlinePayment;
use App\Services\Payments\BanglaQr\EmvQr;
use App\Services\Payments\BanglaQr\StatementParser;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Accounts office: online payment reconciliation, re-query, refunds, and the gateway settings of the branch. */
class OnlinePaymentController extends Controller
{
    public function __construct(protected OnlinePaymentService $service, protected PaymentSettings $settings) {}

    public function index(Request $request): View
    {
        $this->authorize('payments.reconcile');
        $from = $request->date('from') ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to') ?? now();
        $query = OnlinePayment::query()->with(['student:id,name,student_code', 'feePayment:id,receipt_no'])
            ->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())
            ->when($request->input('gateway'), fn ($q, $g) => $q->where('gateway', $g))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('tran_id', 'like', "%$t%")->orWhere('gateway_txn_id', 'like', "%$t%")
                ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%$t%")->orWhere('student_code', 'like', "%$t%"))));

        return view('branch.finance.online-payments.index', [
            'payments' => (clone $query)->latest('id')->paginate(40)->withQueryString(),
            'totals' => (clone $query)->selectRaw('gateway, status, COUNT(*) as n, SUM(COALESCE(paid_amount, amount)) as total')->groupBy('gateway', 'status')->toBase()->get(),
            'review' => OnlinePayment::query()->where('status', 'review')->count(),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function show(OnlinePayment $payment): View
    {
        $this->authorize('payments.reconcile');

        return view('branch.finance.online-payments.show', [
            'payment' => $payment->load(['student', 'feePayment', 'payer:id,name', 'events']),
            'invoices' => $payment->invoices(),
            'canRefundByApi' => $this->settings->gateway($payment->gateway)->supportsRefund(),
        ]);
    }

    /** Bangla QR: upload the bank / wallet statement; matching rows are accepted automatically. */
    public function statement(): View
    {
        $this->authorize('payments.reconcile');
        abort_unless(auth()->user()->can('payments.collect'), 403);

        return view('branch.finance.online-payments.statement', [
            'waiting' => OnlinePayment::query()->where('gateway', 'banglaqr')->whereIn('status', ['review', 'pending', 'initiated'])->count(),
            'result' => session('statement_result'),
        ]);
    }

    public function matchStatement(Request $request): RedirectResponse
    {
        $this->authorize('payments.reconcile');
        abort_unless($request->user()->can('payments.collect'), 403);
        $request->validate(['statement' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        try {
            $rows = StatementParser::parse($request->file('statement')->getRealPath());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['statement' => $e->getMessage()]);
        }
        $result = $this->service->reconcileStatement($rows, $request->user());

        return redirect()->route('branch.finance.online-payments.statement')->with('statement_result', ['rows' => count($rows)] + $result)
            ->with('success', trans_choice(':count payment accepted from the statement.|:count payments accepted from the statement.', count($result['accepted']), ['count' => count($result['accepted'])]));
    }

    public function requery(OnlinePayment $payment): RedirectResponse
    {
        $this->authorize('payments.reconcile');
        $status = $this->service->requery($payment)->status;

        return back()->with('success', __('Checked with the gateway: :status.', ['status' => __(ucfirst($status))]));
    }

    public function refund(Request $request, OnlinePayment $payment): RedirectResponse
    {
        $this->authorize('payments.refund');
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $this->service->refund($payment, $data['reason'], $request->user(), $data['reference'] ?? null);

        return back()->with('success', __('Refund recorded and the receipt reversed.'));
    }

    public function review(Request $request, OnlinePayment $payment): RedirectResponse
    {
        $this->authorize('payments.reconcile');
        abort_unless($request->user()->can('payments.collect'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'note' => ['required', 'string', 'min:5', 'max:200'],
        ]);
        $payment = $this->service->resolveReview($payment, $data['decision'], $data['note'], $request->user());

        return back()->with('success', $payment->status === 'paid'
            ? __('Payment accepted and receipt :receipt posted.', ['receipt' => $payment->feePayment?->receipt_no])
            : __('Payment rejected. Refund the payer from the gateway merchant panel.'));
    }

    public function settings(): View
    {
        $this->authorize('settings.update');

        return view('branch.finance.online-payments.settings', [
            'gateways' => config('payments.gateways'),
            'values' => $this->settings->formValues(),
            'accounts' => Account::query()->money()->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
            'ipnUrl' => route('payments.ipn', 'sslcommerz'),
            'qrInfo' => $this->qrInfo(),
            'shatajCallbackUrl' => route('payments.shatajpay.callback'),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $this->authorize('settings.update');
        $rules = [];
        foreach (config('payments.gateways') as $key => $def) {
            $rules["$key.enabled"] = ['nullable', 'boolean'];
            $rules["$key.sandbox"] = ['nullable', 'boolean'];
            $rules["$key.account_id"] = ['nullable', 'integer', Rule::exists('accounts', 'id')->where('branch_id', tenant()->branchId())->whereNotNull('kind')];
            foreach ($def['fields'] as $field => $f) {
                $rules["$key.$field"] = ['nullable', 'string', ($f['textarea'] ?? false) ? 'max:5000' : 'max:255'];
            }
        }
        $rules['banglaqr.qr_text'][] = function (string $attribute, mixed $value, \Closure $fail) {
            if (filled($value)) {
                try {
                    EmvQr::inspect((string) $value);
                } catch (\InvalidArgumentException $e) {
                    $fail($e->getMessage());
                }
            }
        };
        $data = $request->validate($rules);

        foreach (config('payments.gateways') as $key => $def) {
            $in = $data[$key] ?? [];
            $plain = [];
            $secret = [];
            foreach ($def['fields'] as $field => $f) {
                if ($f['secret'] ?? false) {
                    $secret[] = $field;
                } else {
                    $plain[$field] = filled($in[$field] ?? null) ? trim((string) $in[$field]) : null;
                }
            }
            $this->settings->set($key.'_config', array_filter($plain, fn ($v) => $v !== null));
            $stored = (array) $this->settings->get($key.'_secrets', []);
            foreach ($secret as $field) {
                if (filled($in[$field] ?? null)) {
                    $stored[$field] = trim((string) $in[$field]);
                }
            }
            $this->settings->set($key.'_secrets', $stored, true);
            $this->settings->set($key.'_enabled', (bool) ($in['enabled'] ?? false));
            $this->settings->set($key.'_sandbox', (bool) ($in['sandbox'] ?? false));
            $this->settings->set($key.'_account_id', filled($in['account_id'] ?? null) ? (int) $in['account_id'] : null);
        }

        return back()->with('success', __('Online payment settings saved.'));
    }

    /** What the saved Bangla QR text says (merchant name, city), or null when none / damaged. */
    protected function qrInfo(): ?array
    {
        $text = $this->settings->qrText();
        try {
            return $text ? EmvQr::inspect($text) : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
