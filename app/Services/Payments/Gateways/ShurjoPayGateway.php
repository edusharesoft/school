<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\GatewayException;
use App\Services\Payments\InitiateResult;
use App\Services\Payments\VerifyResult;
use Illuminate\Support\Facades\Cache;

/**
 * shurjoPay v2.
 *   token   POST {base}/get_token {username, password}                   → token, store_id
 *   pay     POST {base}/secret-pay  (Bearer) {prefix, token, store_id, order_id, amount, return_url, cancel_url, customer_* …}
 *                                                                          → checkout_url, sp_order_id
 *   verify  POST {base}/verification (Bearer) {order_id: sp_order_id}   → sp_code 1000 = paid, 1001 declined, 1002 cancelled
 */
class ShurjoPayGateway extends HttpGateway
{
    public function base(array $config): string
    {
        return ($config['sandbox'] ?? true) ? 'https://sandbox.shurjopayment.com/api' : 'https://engine.shurjopayment.com/api';
    }

    public function initiate(OnlinePayment $payment, array $customer, array $urls, array $config): InitiateResult
    {
        [$token, $storeId] = $this->token($config);
        $json = (array) $this->call(fn ($http) => $http->withToken($token)->asJson()->post($this->base($config).'/secret-pay', [
            'prefix' => $config['prefix'] ?: 'SCH',
            'token' => $token,
            'store_id' => $storeId,
            'order_id' => $payment->tran_id,
            'amount' => $this->money($payment->amount),
            'currency' => 'BDT',
            'return_url' => $urls['return'],
            'cancel_url' => $urls['cancel'],
            'customer_name' => $customer['name'],
            'customer_phone' => $customer['phone'],
            'customer_email' => $customer['email'],
            'customer_address' => $customer['address'],
            'customer_city' => $customer['city'],
            'customer_post_code' => $customer['postcode'],
            'client_ip' => $customer['ip'],
            'value1' => $payment->tran_id,
        ]))->json();
        if (blank($json['checkout_url'] ?? null)) {
            throw new GatewayException(__('shurjoPay refused the payment: :reason', ['reason' => $json['message'] ?? $json['sp_message'] ?? 'unknown']), $json);
        }

        return new InitiateResult($json['checkout_url'], $json['sp_order_id'] ?? null, $json);
    }

    public function verify(OnlinePayment $payment, array $input, array $config): VerifyResult
    {
        $orderId = $payment->gateway_ref ?: ($input['order_id'] ?? null);
        if (! $orderId) {
            return VerifyResult::pending('no sp_order_id');
        }
        [$token] = $this->token($config);
        $json = (array) $this->call(fn ($http) => $http->withToken($token)->asJson()->post($this->base($config).'/verification', ['order_id' => $orderId]))->json();
        $row = array_is_list($json) ? (array) ($json[0] ?? []) : $json;   // the API answers with a list of one order
        $code = (string) ($row['sp_code'] ?? '');

        if ($code === '1000') {
            if (($row['customer_order_id'] ?? $payment->tran_id) !== $payment->tran_id) {
                return new VerifyResult('failed', message: 'customer_order_id mismatch', raw: $row);
            }

            return new VerifyResult('paid', (float) ($row['received_amount'] ?? $row['amount'] ?? 0), strtoupper((string) ($row['currency'] ?? 'BDT')),
                (string) ($row['bank_trx_id'] ?? $row['order_id'] ?? ''), $row['method'] ?? null, $row['sp_message'] ?? 'Success', $row);
        }

        return match ($code) {
            '1001' => new VerifyResult('failed', message: $row['sp_message'] ?? 'declined', raw: $row),
            '1002' => new VerifyResult('cancelled', message: $row['sp_message'] ?? 'cancelled', raw: $row),
            default => VerifyResult::pending($row['sp_message'] ?? $code, $row),
        };
    }

    /** @return array{0:string,1:string|int} token and store id, cached per branch for about an hour */
    protected function token(array $config): array
    {
        $this->require($config, ['username', 'password']);
        $key = 'shurjopay_token:'.tenant()->branchId().':'.(($config['sandbox'] ?? true) ? 'sandbox' : 'live').':'.md5($config['username']);

        return Cache::remember($key, 3000, function () use ($config) {
            $json = (array) $this->call(fn ($http) => $http->asJson()->post($this->base($config).'/get_token', ['username' => $config['username'], 'password' => $config['password']]))->json();
            if (blank($json['token'] ?? null)) {
                throw new GatewayException(__('shurjoPay rejected the merchant credentials: :reason', ['reason' => $json['message'] ?? 'unknown']));
            }

            return [(string) $json['token'], $json['store_id'] ?? ''];
        });
    }
}
