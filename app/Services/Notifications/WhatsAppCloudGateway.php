<?php

namespace App\Services\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * WhatsApp Business Platform — Cloud API.
 *   POST https://graph.facebook.com/{version}/{phone-number-id}/messages   (Bearer access token)
 *   {"messaging_product":"whatsapp","to":"8801…","type":"template","template":{"name":…,"language":{"code":…},
 *    "components":[{"type":"body","parameters":[{"type":"text","text":…}, …]}]}}
 *   response {"messages":[{"id":"wamid…"}]}
 * Only approved templates are sent (business-initiated messages outside the 24-hour customer window require them).
 * Delivery status arrives on the webhook (WhatsAppWebhookController).
 */
class WhatsAppCloudGateway
{
    public function sendTemplate(string $to, string $template, string $language, array $parameters, array $config): SendResult
    {
        foreach (['phone_number_id', 'access_token'] as $key) {
            if (blank($config[$key] ?? null)) {
                return SendResult::failed(__('WhatsApp is not configured (:field missing).', ['field' => $key]));
            }
        }
        $version = $config['api_version'] ?? 'v23.0';
        $body = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => array_filter([
                'name' => $template,
                'language' => ['code' => $language],
                'components' => $parameters === [] ? null : [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => mb_substr((string) $v, 0, 1000)], array_values($parameters)),
                ]],
            ]),
        ];

        try {
            $response = Http::timeout(20)->connectTimeout(10)->withToken($config['access_token'])->acceptJson()
                ->post("https://graph.facebook.com/{$version}/{$config['phone_number_id']}/messages", $body);
        } catch (ConnectionException $e) {
            return SendResult::failed('Connection error: '.$e->getMessage(), true);
        }

        $id = $response->json('messages.0.id');
        if ($response->successful() && $id) {
            return SendResult::sent((string) $id);
        }
        $error = $response->json('error.message') ?? ('HTTP '.$response->status());
        $code = (int) $response->json('error.code');

        // 130429 / 131048 / 131056 = throughput / spam rate limits; 5xx = platform trouble.
        return SendResult::failed('WhatsApp '.($code ?: $response->status()).': '.$error, $response->serverError() || in_array($code, [4, 80007, 130429, 131048, 131056], true));
    }
}
