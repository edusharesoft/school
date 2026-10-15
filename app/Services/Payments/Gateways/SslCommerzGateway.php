<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\GatewayException;
use App\Services\Payments\InitiateResult;
use App\Services\Payments\RefundResult;
use App\Services\Payments\VerifyResult;

/**
 * SSLCOMMERZ v4.
 *   initiate  POST {host}/gwprocess/v4/api.php                                → GatewayPageURL, sessionkey
 *   verify    GET  {host}/validator/api/validationserverAPI.php?val_id=…      (after the browser / IPN brings a val_id)
 *             GET  {host}/validator/api/merchantTransIDvalidationAPI.php?tran_id=…   (no val_id: re-query by our tran_id)
 *   refund    GET  {host}/validator/api/merchantTransIDvalidationAPI.php?bank_tran_id=…&refund_amount=…
 *   IPN       POST with verify_sign = md5 of the verify_key fields + md5(store_passwd), sorted by key
 */
class SslCommerzGateway extends HttpGateway
{
    public function host(array $config): string
    {
        return ($config['sandbox'] ?? true) ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
    }

    public function initiate(OnlinePayment $payment, array $customer, array $urls, array $config): InitiateResult
    {
        $this->require($config, ['store_id', 'store_passwd']);
        $response = $this->call(fn ($http) => $http->asForm()->post($this->host($config).'/gwprocess/v4/api.php', [
            'store_id' => $config['store_id'],
            'store_passwd' => $config['store_passwd'],
            'total_amount' => $this->money($payment->amount),
            'currency' => 'BDT',
            'tran_id' => $payment->tran_id,
            'success_url' => $urls['return'],
            'fail_url' => $urls['fail'],
            'cancel_url' => $urls['cancel'],
            'ipn_url' => $urls['ipn'],
            'cus_name' => $customer['name'],
            'cus_email' => $customer['email'],
            'cus_phone' => $customer['phone'],
            'cus_add1' => $customer['address'],
            'cus_city' => $customer['city'],
            'cus_postcode' => $customer['postcode'],
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'num_of_item' => 1,
            'product_name' => $customer['description'],
            'product_category' => 'Education',
            'product_profile' => 'non-physical-goods',
            'value_a' => $payment->tran_id,
        ]));
        $json = (array) $response->json();
        if (strtoupper((string) ($json['status'] ?? '')) !== 'SUCCESS' || blank($json['GatewayPageURL'] ?? null)) {
            throw new GatewayException(__('SSLCOMMERZ refused the payment: :reason', ['reason' => $json['failedreason'] ?? 'HTTP '.$response->status()]), $json);
        }

        return new InitiateResult($json['GatewayPageURL'], $json['sessionkey'] ?? null, $json);
    }

    public function verify(OnlinePayment $payment, array $input, array $config): VerifyResult
    {
        $this->require($config, ['store_id', 'store_passwd']);
        $auth = ['store_id' => $config['store_id'], 'store_passwd' => $config['store_passwd'], 'format' => 'json', 'v' => 1];

        if (filled($input['val_id'] ?? null)) {
            $json = (array) $this->call(fn ($http) => $http->get($this->host($config).'/validator/api/validationserverAPI.php', ['val_id' => $input['val_id']] + $auth))->json();

            return $this->result($payment, $json);
        }

        $json = (array) $this->call(fn ($http) => $http->get($this->host($config).'/validator/api/merchantTransIDvalidationAPI.php', ['tran_id' => $payment->tran_id] + $auth))->json();
        $elements = collect($json['element'] ?? []);
        $valid = $elements->first(fn ($e) => in_array(strtoupper((string) ($e['status'] ?? '')), ['VALID', 'VALIDATED'], true));
        if ($valid) {
            return $this->result($payment, (array) $valid);
        }
        $latest = (array) ($elements->first() ?? []);
        $status = strtoupper((string) ($latest['status'] ?? ''));

        return match ($status) {
            'FAILED' => new VerifyResult('failed', message: $latest['error'] ?? 'FAILED', raw: $json),
            'CANCELLED' => new VerifyResult('cancelled', raw: $json),
            default => VerifyResult::pending($status ?: 'no transaction yet', $json),
        };
    }

    public function supportsRefund(): bool
    {
        return true;
    }

    public function refund(OnlinePayment $payment, float $amount, string $reason, array $config): RefundResult
    {
        $this->require($config, ['store_id', 'store_passwd']);
        $json = (array) $this->call(fn ($http) => $http->get($this->host($config).'/validator/api/merchantTransIDvalidationAPI.php', [
            'bank_tran_id' => $payment->gateway_txn_id,
            'refund_trans_id' => substr('RF'.$payment->tran_id, 0, 30),
            'refund_amount' => $this->money($amount),
            'refund_remarks' => mb_substr($reason, 0, 250),
            'store_id' => $config['store_id'],
            'store_passwd' => $config['store_passwd'],
            'format' => 'json',
        ]))->json();
        $ok = strtoupper((string) ($json['APIConnect'] ?? '')) === 'DONE' && in_array(strtolower((string) ($json['status'] ?? '')), ['success', 'processing'], true);

        return new RefundResult($ok, $json['refund_ref_id'] ?? null, $ok ? ($json['status'] ?? null) : ($json['errorReason'] ?? $json['status'] ?? __('Refund refused')), $json);
    }

    /** IPN signature check: verify_key lists the signed fields; the store password takes part as md5(store_passwd). */
    public function validIpnSignature(array $post, array $config): bool
    {
        if (blank($post['verify_sign'] ?? null) || blank($post['verify_key'] ?? null) || blank($config['store_passwd'] ?? null)) {
            return false;
        }
        $data = [];
        foreach (explode(',', (string) $post['verify_key']) as $key) {
            $data[$key] = (string) ($post[$key] ?? '');
        }
        $data['store_passwd'] = md5((string) $config['store_passwd']);
        ksort($data);
        $string = implode('&', array_map(fn ($k, $v) => $k.'='.$v, array_keys($data), $data));

        return hash_equals(md5($string), (string) $post['verify_sign']);
    }

    protected function result(OnlinePayment $payment, array $json): VerifyResult
    {
        $status = strtoupper((string) ($json['status'] ?? ''));
        if (! in_array($status, ['VALID', 'VALIDATED'], true)) {
            return new VerifyResult($status === 'INVALID_TRANSACTION' ? 'failed' : 'pending', message: $status ?: 'unknown', raw: $json);
        }
        if (($json['tran_id'] ?? null) !== $payment->tran_id) {
            return new VerifyResult('failed', message: 'tran_id mismatch', raw: $json);
        }

        return new VerifyResult('paid', (float) ($json['currency_amount'] ?? $json['amount'] ?? 0), strtoupper((string) ($json['currency_type'] ?? $json['currency'] ?? 'BDT')),
            (string) ($json['bank_tran_id'] ?? $json['val_id'] ?? ''), $json['card_type'] ?? null, $status, $json, (string) ($json['risk_level'] ?? '0') === '1');
    }
}
