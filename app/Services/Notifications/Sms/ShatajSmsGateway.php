<?php

namespace App\Services\Notifications\Sms;

use App\Services\Notifications\SendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * Shataj SMS — our own gateway (Android-device based). All calls are form POSTs with the branch's API key:
 *   send    {server}/services/send.php           number (+8801…), message, key, devices ("1" or "1|0" = device|SIM), type=sms, prioritize
 *   status  {server}/services/read-messages.php  key, id
 * Answers: {"success":true,"data":{"messages":[{"ID":"1","status":"Pending|Queued|Sent|Delivered|Failed",…}]}}
 *          {"success":false,"error":{"message":"…"}}
 * The phone sends the SMS after the API accepted it, so the final status is fetched later (NotificationHub::syncSmsDelivery).
 */
class ShatajSmsGateway extends HttpSmsGateway
{
    public const DEFAULT_SERVER = 'https://sms.shataj.com';

    protected function required(): array
    {
        return ['api_key'];
    }

    public static function server(array $config): string
    {
        return rtrim(($config['server'] ?? null) ?: self::DEFAULT_SERVER, '/');
    }

    protected function request(string $to, string $text, array $config): Response
    {
        return $this->http()->asForm()->post(self::server($config).'/services/send.php', array_filter([
            'number' => '+'.ltrim($to, '+'),
            'message' => $text,
            'key' => $config['api_key'],
            'devices' => $config['device'] ?? null,
            'type' => 'sms',
            'prioritize' => ($config['prioritize'] ?? false) ? 1 : 0,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    protected function interpret(Response $response): SendResult
    {
        $json = $response->json();
        if (! is_array($json)) {
            return SendResult::failed('Shataj SMS HTTP '.$response->status().': '.mb_substr(trim(strip_tags($response->body())) ?: 'empty answer', 0, 200));
        }
        if (! ($json['success'] ?? false)) {
            return SendResult::failed('Shataj SMS: '.($json['error']['message'] ?? 'unknown error'));
        }
        $message = (array) ($json['data']['messages'][0] ?? []);
        $status = self::status($message['status'] ?? null);
        if ($status === 'failed') {
            return SendResult::failed('Shataj SMS: message failed');
        }

        return SendResult::sent(isset($message['ID']) ? (string) $message['ID'] : null, $status === 'delivered' ? 'delivered' : 'sent');
    }

    /**
     * Current state of one message: ['status' => sent|delivered|failed|null, 'raw' => …]; null status = still with the phone.
     */
    public function fetch(string $id, array $config): ?array
    {
        if (blank($config['api_key'] ?? null)) {
            return null;
        }
        try {
            $json = $this->http()->asForm()->post(self::server($config).'/services/read-messages.php', ['key' => $config['api_key'], 'id' => $id])->json();
        } catch (ConnectionException) {
            return null;
        }
        if (! is_array($json) || ! ($json['success'] ?? false)) {
            return null;
        }
        $message = (array) ($json['data']['messages'][0] ?? []);

        return ['status' => self::status($message['status'] ?? null), 'raw' => $message];
    }

    public static function status(?string $status): ?string
    {
        return match (strtolower((string) $status)) {
            'delivered' => 'delivered',
            'sent' => 'sent',
            'failed' => 'failed',
            default => null,   // Pending, Queued, Scheduled, Processing …
        };
    }
}
