<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payments\Gateways\SslCommerzGateway;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Where gateways send the payer back (GET or POST) and server-to-server notifications (IPN).
 * These routes run without a session (a cross-site POST carries no session cookie and must not replace it) and
 * without CSRF; nothing sent here is trusted — the attempt is verified with the gateway before anything is posted.
 */
class GatewayCallbackController extends Controller
{
    public function __construct(protected OnlinePaymentService $service, protected PaymentSettings $settings) {}

    public function return(Request $request, string $gateway, string $tran, string $result): RedirectResponse
    {
        abort_unless(array_key_exists($gateway, config('payments.gateways')), 404);
        $payment = $this->service->handleCallback($gateway, ['tran' => $tran, 'result' => $result] + $request->all(), 'return', $request->ip());

        if (! $payment) {
            return redirect()->route('home.redirect');
        }

        // Started from the mobile app: the browser there has no web login, so show the public result page.
        return ($payment->meta['channel'] ?? 'web') === 'api'
            ? redirect()->route('payments.done', $payment->tran_id)
            : redirect()->route('payments.status', $payment->tran_id);
    }

    /**
     * ShatajPay sends the payer back to one fixed URL per merchant app (set in the ShatajPay panel):
     * /gateway/payment/callback?status=Complete|Failed&orderId=… — the orderId is the random id we sent (gateway_ref).
     */
    public function shatajpay(Request $request): RedirectResponse
    {
        $orderId = (string) $request->query('orderId', '');
        $payment = $orderId !== '' ? \App\Models\Payments\OnlinePayment::query()->where('gateway', 'shatajpay')->where('gateway_ref', $orderId)->first() : null;
        $status = strtolower((string) $request->query('status', ''));
        $input = ['result' => $status === 'complete' ? 'success' : 'fail'] + $request->query();
        if (! $payment) {
            $this->service->log(null, 'return', 'unknown transaction', $request->query(), $request->ip(), 'shatajpay');

            return redirect()->route('home.redirect');
        }

        return $this->return($request->merge($input), 'shatajpay', $payment->tran_id, $input['result']);
    }

    /** Public result page for app payments: status and reference only, nothing personal. */
    public function done(string $tran): \Illuminate\View\View
    {
        $payment = \App\Models\Payments\OnlinePayment::query()->where('tran_id', $tran)->where('created_at', '>=', now()->subDays(2))->firstOrFail();

        return view('portal.payment-done', ['payment' => $payment]);
    }

    public function ipn(Request $request, string $gateway): Response
    {
        abort_unless($gateway === 'sslcommerz', 404);
        $valid = app(SslCommerzGateway::class)->validIpnSignature($request->all(), $this->settings->config('sslcommerz'));
        if (! $valid) {
            $this->service->log(null, 'ipn', 'bad signature', $request->all(), $request->ip(), $gateway);

            return response('invalid signature', 403);
        }
        $this->service->handleCallback($gateway, $request->all(), 'ipn', $request->ip());

        return response('OK');
    }
}
