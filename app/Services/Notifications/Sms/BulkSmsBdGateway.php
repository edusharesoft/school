<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\SendResult;
use Illuminate\Http\Client\Response;

/**
 * BulkSMSBD — POST https://bulksmsbd.net/api/smsapi  (form: api_key, senderid, number, message, type=text)
 * success: {"response_code":202,"success_message":"SMS Submitted Successfully"}; 10xx codes are errors
 * (e.g. 1032 = server IP not whitelisted, 1007 = balance insufficient).
 */
class BulkSmsBdGateway extends HttpSmsGateway
{
    public const URL = 'https://bulksmsbd.net/api/smsapi';

    protected function required(): array
    {
        return ['api_key', 'sender_id'];
    }

    protected function request(string $to, string $text, array $config): Response
    {
        return $this->http()->asForm()->post(self::URL, [
            'api_key' => $config['api_key'],
            'senderid' => $config['sender_id'],
            'number' => $to,
            'message' => $text,
            'type' => 'text',
        ]);
    }

    protected function interpret(Response $response): SendResult
    {
        $json = $response->json() ?? [];
        $code = (int) ($json['response_code'] ?? 0);
        if ($code === 202) {
            return SendResult::sent(isset($json['message_id']) ? (string) $json['message_id'] : null);
        }

        return SendResult::failed('BulkSMSBD '.($code ?: $response->status()).': '.($json['error_message'] ?? $json['success_message'] ?? 'unknown error'));
    }
}
