<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\GatewayException;
use App\Services\Payments\InitiateResult;
use App\Services\Payments\VerifyResult;
use Illuminate\Support\Str;

/**
 * ShatajPay — our own gateway (https://github.com/mspmedisoft/shataj-payment-gateway-integration).
 *   initiate  POST {api_url}/api/v1/user/payment-system
 *             headers: username, password, appkey, appsecret, gatewayid, sandbox (0 = sandbox, 1 = live)
 *             body:    amount, currency, name, email, mobile, Address, opt_value_a/b, transactionId, orderId
 *             answer:  {status: true, status_code: 200, redirect_url}
 *   callback  GET {branch-host}/gateway/payment/callback?status=Complete|Failed&orderId=…
 *             (the callback URL is set for the merchant app in the ShatajPay panel)
 *
 * The callback is an unsigned browser redirect and ShatajPay has no verification API yet, so "Complete" is never
 * posted on its own: the attempt is held for review until the accounts office confirms it in the ShatajPay panel.
 * "Failed" is believed (it can only stop a payment, never record one). When ShatajPay publishes a status API,
 * verify() is the one place to call it.
 */
class ShatajPayGateway extends HttpGateway
{
    public function base(array $config): string
    {
        return rtrim(($config['api_url'] ?? null) ?: 'https://pay.shataj.com', '/');
    }

    public function initiate(OnlinePayment $payment, array $customer, array $urls, array $config): InitiateResult
    {
        $this->require($config, ['username', 'password', 'app_key', 'app_secret', 'gateway_id']);
        // orderId comes back in the callback: a random UUID, so it cannot be guessed from another payment's reference.
        $orderId = (string) Str::uuid();
        $response = $this->call(fn ($http) => $http->withHeaders([
            'username' => $config['username'],
            'password' => $config['password'],
            'appkey' => $config['app_key'],
            'appsecret' => $config['app_secret'],
            'gatewayid' => $config['gateway_id'],
            'sandbox' => ($config['sandbox'] ?? true) ? 0 : 1,
        ])->asJson()->post($this->base($config).'/api/v1/user/payment-system', [
            'amount' => (float) $this->money($payment->amount),
            'currency' => 'BDT',
            'name' => $customer['name'],
            'email' => $customer['email'],
            'mobile' => $customer['phone'],
            'Address' => $customer['address'],
            'opt_value_a' => $payment->tran_id,
            'opt_value_b' => $customer['reference'],
            'transactionId' => $payment->tran_id,
            'orderId' => $orderId,
        ]));
        $json = (array) $response->json();
        if (! $response->successful() || ($json['status'] ?? null) !== true || (int) ($json['status_code'] ?? 0) !== 200 || blank($json['redirect_url'] ?? null)) {
            throw new GatewayException(__('ShatajPay refused the payment: :reason', ['reason' => $json['message'] ?? 'HTTP '.$response->status()]), $json);
        }

        return new InitiateResult($json['redirect_url'], $orderId, $json);
    }

    public function verify(OnlinePayment $payment, array $input, array $config): VerifyResult
    {
        $status = strtolower((string) ($input['status'] ?? ''));

        return match ($status) {
            'complete', 'completed', 'success' => new VerifyResult('paid', $payment->amount, 'BDT', null, 'ShatajPay', 'Complete', $input,
                reviewReason: __('ShatajPay reported the payment as complete through the browser. Confirm it in the ShatajPay merchant panel, then accept it here.')),
            'failed', 'failure' => new VerifyResult('failed', message: 'Failed', raw: $input),
            'cancel', 'cancelled' => new VerifyResult('cancelled', message: 'Cancelled', raw: $input),
            default => VerifyResult::pending(__('ShatajPay has no status API; waiting for its callback.')),
        };
    }
}
