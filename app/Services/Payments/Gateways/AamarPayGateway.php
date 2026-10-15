<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\GatewayException;
use App\Services\Payments\InitiateResult;
use App\Services\Payments\VerifyResult;

/**
 * aamarPay.
 *   initiate  POST {host}/jsonpost.php (JSON, type=json)                          → result "true", payment_url
 *   verify    GET  {host}/api/v1/trxcheck/request.php?request_id={tran_id}&store_id&signature_key&type=json
 *             status_code 2 = successful, 7 = failed, 3 = expired, 0 = initiated
 */
class AamarPayGateway extends HttpGateway
{
    public function host(array $config): string
    {
        return ($config['sandbox'] ?? true) ? 'https://sandbox.aamarpay.com' : 'https://secure.aamarpay.com';
    }

    public function initiate(OnlinePayment $payment, array $customer, array $urls, array $config): InitiateResult
    {
        $this->require($config, ['store_id', 'signature_key']);
        $json = (array) $this->call(fn ($http) => $http->asJson()->post($this->host($config).'/jsonpost.php', [
            'store_id' => $config['store_id'],
            'signature_key' => $config['signature_key'],
            'tran_id' => $payment->tran_id,
            'amount' => $this->money($payment->amount),
            'currency' => 'BDT',
            'desc' => $customer['description'],
            'cus_name' => $customer['name'],
            'cus_email' => $customer['email'],
            'cus_phone' => $customer['phone'],
            'cus_add1' => $customer['address'],
            'cus_city' => $customer['city'],
            'cus_country' => 'Bangladesh',
            'success_url' => $urls['return'],
            'fail_url' => $urls['fail'],
            'cancel_url' => $urls['cancel'],
            'opt_a' => $payment->tran_id,
            'type' => 'json',
        ]))->json();
        if ((string) ($json['result'] ?? '') !== 'true' || blank($json['payment_url'] ?? null)) {
            $reason = is_array($json['result'] ?? null) ? implode(' ', $json['result']) : ($json['result'] ?? $json['message'] ?? 'unknown');
            throw new GatewayException(__('aamarPay refused the payment: :reason', ['reason' => $reason]), $json);
        }

        return new InitiateResult($json['payment_url'], null, $json);
    }

    public function verify(OnlinePayment $payment, array $input, array $config): VerifyResult
    {
        $this->require($config, ['store_id', 'signature_key']);
        $json = (array) $this->call(fn ($http) => $http->get($this->host($config).'/api/v1/trxcheck/request.php', [
            'request_id' => $payment->tran_id, 'store_id' => $config['store_id'], 'signature_key' => $config['signature_key'], 'type' => 'json',
        ]))->json();
        $code = (string) ($json['status_code'] ?? '');
        $status = (string) ($json['pay_status'] ?? '');

        if ($code === '2' || $status === 'Successful') {
            if (($json['mer_txnid'] ?? $payment->tran_id) !== $payment->tran_id) {
                return new VerifyResult('failed', message: 'mer_txnid mismatch', raw: $json);
            }

            return new VerifyResult('paid', (float) ($json['amount'] ?? 0), strtoupper((string) ($json['currency'] ?? 'BDT')),
                (string) ($json['pg_txnid'] ?? ''), $json['payment_type'] ?? null, $status, $json);
        }

        return match ($code) {
            '7' => new VerifyResult('failed', message: $status ?: 'Failed', raw: $json),
            '3' => new VerifyResult('failed', message: $status ?: 'Expired', raw: $json),
            default => VerifyResult::pending($status ?: ($json['message'] ?? null), $json),
        };
    }
}
