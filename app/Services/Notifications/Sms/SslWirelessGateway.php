<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\SendResult;
use Illuminate\Http\Client\Response;

/**
 * SSL Wireless ISMS Plus — POST https://smsplus.sslwireless.com/api/v3/send-sms (JSON)
 * {api_token, sid, msisdn, sms, csms_id}; success: {"status":"SUCCESS","smsinfo":[{"sms_status":"SUCCESS","reference_id":…}]}
 */
class SslWirelessGateway extends HttpSmsGateway
{
    public const URL = 'https://smsplus.sslwireless.com/api/v3/send-sms';

    protected function required(): array
    {
        return ['api_token', 'sid'];
    }

    protected function request(string $to, string $text, array $config): Response
    {
        return $this->http()->asJson()->post(self::URL, [
            'api_token' => $config['api_token'],
            'sid' => $config['sid'],
            'msisdn' => $to,
            'sms' => $text,
            'csms_id' => substr(bin2hex(random_bytes(10)), 0, 20),   // our unique id for this SMS
        ]);
    }

    protected function interpret(Response $response): SendResult
    {
        $json = $response->json() ?? [];
        $info = $json['smsinfo'][0] ?? [];
        if (strtoupper((string) ($json['status'] ?? '')) === 'SUCCESS' && strtoupper((string) ($info['sms_status'] ?? 'SUCCESS')) === 'SUCCESS') {
            return SendResult::sent((string) ($info['reference_id'] ?? $info['sms_id'] ?? $info['csms_id'] ?? ''));
        }

        return SendResult::failed('SSL Wireless '.($json['status_code'] ?? $response->status()).': '.($json['error_message'] ?? $info['sms_status'] ?? 'unknown error'));
    }
}
