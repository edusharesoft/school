<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Communication\NotificationMessage;
use App\Services\Notifications\NotificationSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * WhatsApp Cloud API webhook of a branch (https://{branch-host}/webhooks/whatsapp).
 *   GET  subscription check: hub.mode=subscribe & hub.verify_token → echo hub.challenge
 *   POST status updates, signed with X-Hub-Signature-256 = sha256 HMAC of the raw body with the app secret.
 * Updates are idempotent: a status never moves backwards (sent → delivered → read; failed is final).
 */
class WhatsAppWebhookController extends Controller
{
    protected const RANK = ['sending' => 0, 'queued' => 0, 'sent' => 1, 'delivered' => 2, 'failed' => 3];

    public function verify(Request $request, NotificationSettings $settings): Response
    {
        $token = $settings->whatsappConfig()['verify_token'] ?? null;
        abort_unless($request->query('hub_mode') === 'subscribe' && $token && hash_equals((string) $token, (string) $request->query('hub_verify_token')), 403);

        return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, NotificationSettings $settings): JsonResponse
    {
        $secret = $settings->whatsappConfig()['app_secret'] ?? null;
        $signature = (string) $request->header('X-Hub-Signature-256');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);
        abort_unless($secret && hash_equals($expected, $signature), 403);

        $updated = 0;
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    $updated += $this->apply($status);
                }
            }
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    protected function apply(array $status): int
    {
        $message = NotificationMessage::query()->where('provider', 'whatsapp_cloud')->where('provider_message_id', (string) ($status['id'] ?? ''))->first();
        if (! $message) {
            return 0;
        }
        $state = (string) ($status['status'] ?? '');
        $at = isset($status['timestamp']) ? \Carbon\Carbon::createFromTimestamp((int) $status['timestamp']) : now();

        if ($state === 'read') {
            $message->forceFill(['read_at' => $message->read_at ?? $at, 'delivered_at' => $message->delivered_at ?? $at,
                'status' => $message->status === 'failed' ? 'failed' : 'delivered'])->save();

            return 1;
        }
        $new = match ($state) { 'sent' => 'sent', 'delivered' => 'delivered', 'failed' => 'failed', default => null };
        if (! $new || (self::RANK[$new] ?? 0) <= (self::RANK[$message->status] ?? 0)) {
            return 0;
        }
        $message->forceFill([
            'status' => $new,
            'delivered_at' => $new === 'delivered' ? $at : $message->delivered_at,
            'error' => $new === 'failed' ? mb_substr((string) ($status['errors'][0]['title'] ?? $status['errors'][0]['message'] ?? 'failed'), 0, 255) : $message->error,
        ])->save();

        return 1;
    }
}
