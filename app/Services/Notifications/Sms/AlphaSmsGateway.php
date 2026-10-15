<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\SendResult;
use Illuminate\Http\Client\Response;

/**
 * Alpha SMS (sms.net.bd) — POST https://api.sms.net.bd/sendsms  (form: api_key, msg, to, sender_id?)
 * success: {"error":0,"msg":"Request successfully submitted","data":{"request_id":…}}; error 417 = insufficient balance.
 */
class AlphaSmsGateway extends HttpSmsGateway
{
    public const URL = 'https://api.sms.net.bd/sendsms';

    protected function required(): array
    {
        return ['api_key'];
    }

    protected function request(string $to, string $text, array $config): Response
    {
        return $this->http()->asForm()->post(self::URL, array_filter([
            'api_key' => $config['api_key'],
            'msg' => $text,
            'to' => $to,
            'sender_id' => $config['sender_id'] ?? null,
        ]));
    }

    protected function interpret(Response $response): SendResult
    {
        $json = $response->json() ?? [];
        if (array_key_exists('error', $json) && (int) $json['error'] === 0) {
            return SendResult::sent(isset($json['data']['request_id']) ? (string) $json['data']['request_id'] : null);
        }

        return SendResult::failed('Alpha SMS '.($json['error'] ?? $response->status()).': '.($json['msg'] ?? 'unknown error'));
    }
}
