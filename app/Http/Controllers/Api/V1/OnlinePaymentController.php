<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payments\OnlinePayment;
use App\Models\Students\Student;
use App\Services\Payments\BanglaQr\EmvQr;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentSettings;
use App\Services\Portal\PortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Online fee payment for the mobile app (student / parent tokens).
 *   GET  /api/v1/my/online-payments/gateways   enabled gateways of the branch
 *   POST /api/v1/my/online-payments            {student_id, gateway, invoice_ids[]} → {tran_id, redirect_url}
 *   GET  /api/v1/my/online-payments/{tran}     status of an attempt (asks the gateway again while it is open)
 *   POST /api/v1/my/online-payments/{tran}/claim   Bangla QR: {trx_id, amount?, payer_account?} — "I paid", goes to the accounts office
 * Bangla QR (manual gateway) has no redirect: the answer carries qr = {payload, merchant, city} to draw as a QR code in the app.
 * The app opens redirect_url in a browser; afterwards the payer lands on a public result page and the app polls the status.
 */
class OnlinePaymentController extends Controller
{
    use ApiResponse;

    public function __construct(protected OnlinePaymentService $service, protected PaymentSettings $settings, protected PortalService $portal) {}

    public function gateways(): JsonResponse
    {
        return $this->ok(collect($this->settings->enabled())->map(fn ($g) => ['key' => $g, 'name' => config("payments.gateways.$g.short"), 'manual' => $this->settings->isManual($g)])->values());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'gateway' => ['required', Rule::in($this->settings->enabled())],
            'invoice_ids' => ['required', 'array', 'min:1', 'max:24'],
            'invoice_ids.*' => ['integer'],
        ]);
        $student = Student::query()->find($data['student_id']);
        $this->assertOwn($request, $student);
        [$payment, $url] = $this->service->start($student, array_map('intval', $data['invoice_ids']), $data['gateway'], $request->user(), (string) $request->ip(), 'api');

        if ($this->settings->isManual($payment->gateway)) {
            $info = EmvQr::inspect((string) $this->settings->qrText());

            return $this->ok(['tran_id' => $payment->tran_id, 'amount' => $payment->amount, 'redirect_url' => null,
                'qr' => ['payload' => $this->service->qrPayload($payment), 'merchant' => $info['name'], 'city' => $info['city']]], null, 201);
        }

        return $this->ok(['tran_id' => $payment->tran_id, 'amount' => $payment->amount, 'redirect_url' => $url], null, 201);
    }

    public function claim(Request $request, string $tran): JsonResponse
    {
        $payment = OnlinePayment::query()->where('tran_id', $tran)->firstOrFail();
        $this->assertOwn($request, $payment->student);
        abort_unless($this->settings->isManual($payment->gateway), 404);
        $data = $request->validate([
            'trx_id' => ['required', 'string', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-_\/]{5,39}$/'],
            'amount' => ['nullable', 'numeric', 'min:1', 'max:9999999'],
            'payer_account' => ['nullable', 'string', 'max:40'],
        ]);
        $payment = $this->service->submitClaim($payment, $data, (string) $request->ip());

        return $this->ok(['tran_id' => $payment->tran_id, 'status' => $payment->status]);
    }

    public function show(Request $request, string $tran): JsonResponse
    {
        $payment = OnlinePayment::query()->where('tran_id', $tran)->with('feePayment:id,receipt_no')->firstOrFail();
        $this->assertOwn($request, $payment->student);
        if ($payment->isOpen() && $payment->created_at->gt(now()->subDay())) {
            $payment = $this->service->requery($payment)->load('feePayment:id,receipt_no');
        }

        return $this->ok([
            'tran_id' => $payment->tran_id, 'gateway' => $payment->gateway, 'status' => $payment->status, 'amount' => $payment->amount,
            'paid_amount' => $payment->paid_amount, 'gateway_txn_id' => $payment->gateway_txn_id, 'receipt_no' => $payment->feePayment?->receipt_no,
            'created_at' => $payment->created_at->toIso8601String(), 'paid_at' => $payment->paid_at?->toIso8601String(),
        ]);
    }

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
