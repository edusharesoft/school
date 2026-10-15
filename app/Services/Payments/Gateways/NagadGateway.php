<?php

namespace App\Services\Payments\Gateways;

use App\Models\Payments\OnlinePayment;
use App\Services\Payments\GatewayException;
use App\Services\Payments\InitiateResult;
use App\Services\Payments\VerifyResult;
use Illuminate\Http\Client\PendingRequest;

/**
 * Nagad merchant checkout (API v-0.2.0).
 *   initialize  POST {base}/check-out/initialize/{merchantId}/{orderId}
 *               {accountNumber, dateTime, sensitiveData, signature}
 *               sensitiveData = base64(RSA-PKCS1 encrypt with Nagad's public key of {merchantId, datetime, orderId, challenge})
 *               signature     = base64(SHA256withRSA of the same JSON with the merchant private key)
 *               answer: sensitiveData encrypted for the merchant → {paymentReferenceId, challenge}
 *   complete    POST {base}/check-out/complete/{paymentReferenceId}
 *               sensitiveData {merchantId, orderId, currencyCode 050, amount, challenge} + merchantCallbackURL → callBackUrl
 *   callback    GET  {merchantCallbackURL}&order_id&payment_ref_id&status&status_code
 *   verify      GET  {base}/verify/payment/{paymentReferenceId} → status Success, amount, issuerPaymentRefNo
 * Every request carries X-KM-Api-Version, X-KM-IP-V4 and X-KM-Client-Type.
 */
class NagadGateway extends HttpGateway
{
    public function base(array $config): string
    {
        $url = ($config['sandbox'] ?? true) ? ($config['sandbox_url'] ?? null) : ($config['live_url'] ?? null);
        $default = ($config['sandbox'] ?? true) ? config('payments.gateways.nagad.fields.sandbox_url.default') : config('payments.gateways.nagad.fields.live_url.default');

        return rtrim($url ?: $default, '/');
    }

    public function initiate(OnlinePayment $payment, array $customer, array $urls, array $config): InitiateResult
    {
        $this->require($config, ['merchant_id', 'merchant_number', 'merchant_private_key', 'nagad_public_key']);
        $now = now('Asia/Dhaka')->format('YmdHis');
        $challenge = bin2hex(random_bytes(20));
        $plain = json_encode(['merchantId' => $config['merchant_id'], 'datetime' => $now, 'orderId' => $payment->tran_id, 'challenge' => $challenge]);

        $init = (array) $this->call(fn ($http) => $this->headers($http, $config, $customer['ip'])
            ->post($this->base($config).'/check-out/initialize/'.$config['merchant_id'].'/'.$payment->tran_id.'?locale=EN', [
                'accountNumber' => $config['merchant_number'],
                'dateTime' => $now,
                'sensitiveData' => $this->encrypt($plain, $config),
                'signature' => $this->sign($plain, $config),
            ]))->json();
        if (blank($init['sensitiveData'] ?? null)) {
            throw new GatewayException(__('Nagad refused the payment: :reason', ['reason' => $init['message'] ?? $init['reason'] ?? 'unknown']), $init);
        }
        $reply = $this->decrypt($init['sensitiveData'], $config);
        // Nagad signs the plain JSON it encrypted for us with its private key.
        if (blank($init['signature'] ?? null) || ! $this->verifySignature($reply, $init['signature'], $config)) {
            throw new GatewayException(__('Nagad answer could not be verified.'), ['stage' => 'initialize']);
        }
        $decrypted = json_decode($reply, true) ?: [];
        $reference = $decrypted['paymentReferenceId'] ?? null;
        if (! $reference) {
            throw new GatewayException(__('Nagad did not return a payment reference.'), ['stage' => 'initialize']);
        }

        $order = json_encode(['merchantId' => $config['merchant_id'], 'orderId' => $payment->tran_id, 'currencyCode' => '050',
            'amount' => $this->money($payment->amount), 'challenge' => $decrypted['challenge'] ?? $challenge]);
        $complete = (array) $this->call(fn ($http) => $this->headers($http, $config, $customer['ip'])
            ->post($this->base($config).'/check-out/complete/'.$reference, [
                'sensitiveData' => $this->encrypt($order, $config),
                'signature' => $this->sign($order, $config),
                'merchantCallbackURL' => $urls['return'],
                'additionalMerchantInfo' => ['tran' => $payment->tran_id, 'student' => $customer['reference']],
            ]))->json();
        if (strtolower((string) ($complete['status'] ?? '')) !== 'success' || blank($complete['callBackUrl'] ?? null)) {
            throw new GatewayException(__('Nagad refused the payment: :reason', ['reason' => $complete['message'] ?? $complete['reason'] ?? 'unknown']), $complete);
        }

        return new InitiateResult($complete['callBackUrl'], (string) $reference, ['initialize' => ['paymentReferenceId' => $reference], 'complete' => $complete]);
    }

    public function verify(OnlinePayment $payment, array $input, array $config): VerifyResult
    {
        $reference = $payment->gateway_ref ?: ($input['payment_ref_id'] ?? null);
        if (! $reference) {
            return VerifyResult::pending('no paymentReferenceId');
        }
        $json = (array) $this->call(fn ($http) => $this->headers($http, $config, request()?->ip() ?? '127.0.0.1')
            ->get($this->base($config).'/verify/payment/'.$reference))->json();
        $status = strtolower((string) ($json['status'] ?? ''));

        if ($status === 'success') {
            if (($json['orderId'] ?? $payment->tran_id) !== $payment->tran_id) {
                return new VerifyResult('failed', message: 'orderId mismatch', raw: $json);
            }

            return new VerifyResult('paid', (float) ($json['amount'] ?? 0), 'BDT', (string) ($json['issuerPaymentRefNo'] ?? $json['paymentRefId'] ?? $reference), 'Nagad', 'Success', $json);
        }

        return match ($status) {
            'aborted', 'cancelled' => new VerifyResult('cancelled', message: $json['status'], raw: $json),
            'failed', 'invalidrequest', 'fraud' => new VerifyResult('failed', message: $json['status'] ?? null, raw: $json),
            default => VerifyResult::pending($json['status'] ?? null, $json),
        };
    }

    protected function headers(PendingRequest $http, array $config, string $ip): PendingRequest
    {
        return $http->asJson()->withHeaders([
            'X-KM-Api-Version' => 'v-0.2.0',
            'X-KM-IP-V4' => $config['server_ip'] ?: $ip,
            'X-KM-Client-Type' => 'PC_WEB',
        ]);
    }

    public function encrypt(string $plain, array $config): string
    {
        $key = openssl_pkey_get_public($this->pem($config['nagad_public_key'], 'PUBLIC KEY'));
        if (! $key || ! openssl_public_encrypt($plain, $encrypted, $key, OPENSSL_PKCS1_PADDING)) {
            throw new GatewayException(__('The Nagad public key is not valid.'));
        }

        return base64_encode($encrypted);
    }

    public function decrypt(string $cipher, array $config): string
    {
        $key = openssl_pkey_get_private($this->pem($config['merchant_private_key'], 'PRIVATE KEY'));
        if (! $key || ! openssl_private_decrypt(base64_decode($cipher), $plain, $key, OPENSSL_PKCS1_PADDING)) {
            throw new GatewayException(__('The Nagad answer could not be decrypted with the merchant private key.'));
        }

        return $plain;
    }

    public function sign(string $plain, array $config): string
    {
        $key = openssl_pkey_get_private($this->pem($config['merchant_private_key'], 'PRIVATE KEY'));
        if (! $key || ! openssl_sign($plain, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new GatewayException(__('The merchant private key is not valid.'));
        }

        return base64_encode($signature);
    }

    protected function verifySignature(string $plain, string $signature, array $config): bool
    {
        $key = openssl_pkey_get_public($this->pem($config['nagad_public_key'], 'PUBLIC KEY'));

        return $key && openssl_verify($plain, base64_decode($signature), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /** Keys are often pasted as bare base64; wrap them in PEM armour. */
    protected function pem(string $key, string $type): string
    {
        $key = trim($key);
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN {$type}-----\n".chunk_split(preg_replace('/\s+/', '', $key), 64, "\n")."-----END {$type}-----\n";
    }
}
