<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\GatewayException;
use App\Services\Payments\InitiateResult;
use App\Services\Payments\RefundResult;
use App\Services\Payments\VerifyResult;
use Illuminate\Support\Facades\Cache;

/**
 * bKash Tokenized Checkout (v1.2.0-beta).
 *   token    POST /tokenized/checkout/token/grant   (headers username, password; body app_key, app_secret) → id_token
 *   create   POST /tokenized/checkout/create        (mode 0011, intent sale, callbackURL …)                 → paymentID, bkashURL
 *   callback GET  {callbackURL}&paymentID=…&status=success|failure|cancel
 *   execute  POST /tokenized/checkout/execute       {paymentID} → transactionStatus Completed, trxID
 *   status   POST /tokenized/checkout/payment/status {paymentID}  (after a lost execute response, or for reconciliation)
 *   refund   POST /tokenized/checkout/payment/refund {paymentID, trxID, amount, sku, reason}
 */
class BkashGateway extends HttpGateway
{
    public function base(array $config): string
    {
        return ($config['sandbox'] ?? true) ? 'https://tokenized.sandbox.bka.sh/v1.2.0-beta' : 'https://tokenized.pay.bka.sh/v1.2.0-beta';
    }

    public function initiate(OnlinePayment $payment, array $customer, array $urls, array $config): InitiateResult
    {
        $json = $this->post($config, '/tokenized/checkout/create', [
            'mode' => '0011',
            'payerReference' => mb_substr($customer['reference'], 0, 50),
            'callbackURL' => $urls['return'],
            'amount' => $this->money($payment->amount),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $payment->tran_id,
        ]);
        if (($json['statusCode'] ?? null) !== '0000' || blank($json['bkashURL'] ?? null)) {
            throw new GatewayException(__('bKash refused the payment: :reason', ['reason' => $json['statusMessage'] ?? $json['errorMessage'] ?? 'unknown']), $json);
        }

        return new InitiateResult($json['bkashURL'], $json['paymentID'] ?? null, $json);
    }

    public function verify(OnlinePayment $payment, array $input, array $config): VerifyResult
    {
        $paymentId = $payment->gateway_ref;
        if (! $paymentId) {
            return VerifyResult::pending('no paymentID');
        }
        $callback = strtolower((string) ($input['status'] ?? ''));

        // The payer approved in the bKash app: execute once. A lost / failed execute is followed by a status query.
        if ($callback === 'success' && ! ($payment->meta['bkash_executed'] ?? false)) {
            $json = $this->post($config, '/tokenized/checkout/execute', ['paymentID' => $paymentId]);
            if (($json['statusCode'] ?? null) === '0000' && ($json['transactionStatus'] ?? null) === 'Completed') {
                return $this->paid($payment, $json);
            }
        }

        $json = $this->post($config, '/tokenized/checkout/payment/status', ['paymentID' => $paymentId]);
        $status = (string) ($json['transactionStatus'] ?? '');
        if ($status === 'Completed') {
            return $this->paid($payment, $json);
        }
        if ($callback === 'cancel') {
            return new VerifyResult('cancelled', message: $status ?: 'cancelled', raw: $json);
        }
        if ($callback === 'failure' || in_array($status, ['Failed', 'Cancelled', 'Expired'], true)) {
            return new VerifyResult($status === 'Cancelled' ? 'cancelled' : 'failed', message: $json['statusMessage'] ?? $status, raw: $json);
        }

        return VerifyResult::pending($status ?: ($json['statusMessage'] ?? null), $json);
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function refund(OnlinePayment $payment, float $amount, string $reason, array $config): RefundResult
    {
        $json = $this->post($config, '/tokenized/checkout/payment/refund', [
            'paymentID' => $payment->gateway_ref,
            'trxID' => $payment->gateway_txn_id,
            'amount' => $this->money($amount),
            'sku' => 'fees',
            'reason' => mb_substr($reason, 0, 255),
        ]);
        $ok = ($json['transactionStatus'] ?? null) === 'Completed';

        return new RefundResult($ok, $json['refundTrxID'] ?? null, $ok ? null : ($json['statusMessage'] ?? $json['errorMessage'] ?? __('Refund refused')), $json);
    }

    protected function paid(OnlinePayment $payment, array $json): VerifyResult
    {
        if (($json['merchantInvoiceNumber'] ?? $payment->tran_id) !== $payment->tran_id) {
            return new VerifyResult('failed', message: 'merchantInvoiceNumber mismatch', raw: $json);
        }

        return new VerifyResult('paid', (float) ($json['amount'] ?? 0), strtoupper((string) ($json['currency'] ?? 'BDT')), (string) ($json['trxID'] ?? ''), 'bKash',
            'Completed', $json + ['bkash_executed' => true]);
    }

    protected function post(array $config, string $path, array $body): array
    {
        $this->require($config, ['username', 'password', 'app_key', 'app_secret']);
        $response = $this->call(fn ($http) => $http->withHeaders(['Authorization' => $this->token($config), 'X-APP-Key' => $config['app_key']])
            ->asJson()->post($this->base($config).$path, $body));

        return (array) $response->json();
    }

    /** id_token, cached per branch and environment for its lifetime (1 hour) minus a margin. */
    protected function token(array $config): string
    {
        $key = 'bkash_token:'.tenant()->branchId().':'.(($config['sandbox'] ?? true) ? 'sandbox' : 'live').':'.md5($config['app_key']);

        return Cache::remember($key, 3000, function () use ($config) {
            $response = $this->call(fn ($http) => $http->withHeaders(['username' => $config['username'], 'password' => $config['password']])
                ->asJson()->post($this->base($config).'/tokenized/checkout/token/grant', ['app_key' => $config['app_key'], 'app_secret' => $config['app_secret']]));
            $token = $response->json('id_token');
            if (! $token) {
                throw new GatewayException(__('bKash rejected the merchant credentials: :reason', ['reason' => $response->json('statusMessage') ?? $response->json('msg') ?? 'HTTP '.$response->status()]));
            }

            return (string) $token;
        });
    }
}
