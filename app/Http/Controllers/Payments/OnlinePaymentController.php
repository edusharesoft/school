<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payments\OnlinePayment;
use App\Models\Students\Student;
use App\Services\Payments\BanglaQr\EmvQr;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentSettings;
use App\Services\Portal\PortalService;
use App\Support\Qr\QrEncoder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Parents and students pay open invoices online and see the result of their attempt. */
class OnlinePaymentController extends Controller
{
    public function __construct(protected OnlinePaymentService $service, protected PaymentSettings $settings, protected PortalService $portal) {}

    public function start(Request $request, Student $student): RedirectResponse
    {
        $this->assertOwn($request, $student);
        $data = $request->validate([
            'gateway' => ['required', Rule::in($this->settings->enabled())],
            'invoice_ids' => ['required', 'array', 'min:1', 'max:24'],
            'invoice_ids.*' => ['integer'],
        ]);
        [, $url] = $this->service->start($student, array_map('intval', $data['invoice_ids']), $data['gateway'], $request->user(), (string) $request->ip());

        return redirect()->away($url);
    }

    /** The Bangla QR page: the QR with the exact amount, how to pay, and the form for the TrxID. */
    public function qr(Request $request, string $tran): View|RedirectResponse
    {
        $payment = $this->manualPayment($request, $tran);
        if (! in_array($payment->status, ['initiated', 'pending', 'expired'], true)) {
            return redirect()->route('payments.status', $payment->tran_id);
        }
        $payload = $this->service->qrPayload($payment);
        $info = EmvQr::inspect((string) $this->settings->qrText());

        return view('portal.payment-qr', [
            'payment' => $payment, 'invoices' => $payment->invoices(), 'svg' => QrEncoder::svg($payload, 300),
            'merchant' => $info, 'instructions' => (string) (((array) $this->settings->get('banglaqr_config', []))['instructions'] ?? ''),
            'feesUrl' => $request->user()->portal() === 'parent' ? route('portal.parent.fees', $payment->student_id) : route('portal.student.fees'),
        ]);
    }

    /** "I paid": the TrxID goes to the accounts office, which confirms it against the bank / wallet statement. */
    public function claim(Request $request, string $tran): RedirectResponse
    {
        $payment = $this->manualPayment($request, $tran);
        $data = $request->validate([
            'trx_id' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-_\/]{5,39}$/'],
            'amount' => ['nullable', 'numeric', 'min:1', 'max:9999999'],
            'payer_account' => ['nullable', 'string', 'max:40'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
        ], ['trx_id.regex' => __('The transaction ID has 6–40 letters or digits, as in the confirmation message of your app.')]);
        $this->service->submitClaim($payment, $data, (string) $request->ip());

        return redirect()->route('payments.status', $payment->tran_id)->with('success', __('Thank you. The accounts office will check your payment and add the receipt.'));
    }

    protected function manualPayment(Request $request, string $tran): OnlinePayment
    {
        $payment = OnlinePayment::query()->where('tran_id', $tran)->with('student')->firstOrFail();
        $this->assertOwn($request, $payment->student);
        abort_unless($this->settings->isManual($payment->gateway), 404);

        return $payment;
    }

    public function status(Request $request, string $tran): View
    {
        $payment = OnlinePayment::query()->where('tran_id', $tran)->with(['student', 'feePayment'])->firstOrFail();
        $this->assertOwn($request, $payment->student);
        // The payer came back before the gateway confirmed: ask once more.
        if ($payment->isOpen() && $payment->created_at->gt(now()->subDay())) {
            $payment = $this->service->requery($payment)->load(['student', 'feePayment']);
        }

        return view('portal.payment-status', [
            'payment' => $payment,
            'receiptUrl' => $payment->feePayment ? route($request->user()->portal() === 'parent' ? 'portal.parent.fees.receipt' : 'portal.student.fees.receipt', $payment->feePayment) : null,
            'feesUrl' => $request->user()->portal() === 'parent' ? route('portal.parent.fees', $payment->student_id) : route('portal.student.fees'),
        ]);
    }

    /** A parent pays for their own children; a student only for themself. Anything else is "not found". */
    protected function assertOwn(Request $request, ?Student $student): void
    {
        $user = $request->user();
        $ok = $student && match ($user->portal()) {
            'parent' => ($guardian = $this->portal->guardianFor($user)) && $this->portal->isChildOf($guardian, $student),
            'student' => $this->portal->studentFor($user)?->id === $student->id,
            default => false,
        };
        abort_unless($ok, 404);
    }
}
