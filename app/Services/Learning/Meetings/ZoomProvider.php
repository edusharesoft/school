<?php

namespace App\Services\Learning\Meetings;

use App\Models\Learning\OnlineClass;
use App\Models\Learning\OnlineClassIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Zoom, Server-to-Server OAuth (account credentials grant): no user login, one app per school account.
 * Access tokens last one hour and are cached (encrypted) on the integration row.
 */
class ZoomProvider implements MeetingProvider
{
    public function __construct(protected OnlineClassIntegration $integration) {}

    public function create(OnlineClass $class): MeetingDetails
    {
        $host = $this->integration->setting('host_user') ?: 'me';
        $data = $this->json($this->api()->post($this->url("/users/".rawurlencode($host)."/meetings"), $this->payload($class)), 'create the Zoom meeting');

        if (empty($data['id']) || empty($data['join_url'])) {
            throw new MeetingException(__('Zoom did not return a meeting link.'));
        }

        return new MeetingDetails((string) $data['id'], $data['join_url'], $data['start_url'] ?? null, (string) $data['id'], $data['password'] ?? null);
    }

    public function update(OnlineClass $class): void
    {
        $this->json($this->api()->patch($this->url('/meetings/'.$class->external_id), $this->payload($class)), 'update the Zoom meeting');
    }

    public function cancel(OnlineClass $class): void
    {
        $response = $this->api()->delete($this->url('/meetings/'.$class->external_id));
        if ($response->status() === 404) {
            return;   // already gone
        }
        $this->json($response, 'cancel the Zoom meeting');
    }

    public function recording(OnlineClass $class): ?string
    {
        $response = $this->api()->get($this->url('/meetings/'.$class->external_id.'/recordings'));
        if ($response->status() === 404) {
            return null;   // not recorded (yet)
        }
        $data = $this->json($response, 'read the Zoom recording');

        return $data['share_url'] ?? null;
    }

    public function check(): void
    {
        $this->json($this->api()->get($this->url('/users/me')), 'reach Zoom');
    }

    protected function payload(OnlineClass $class): array
    {
        $tz = config('app.timezone');

        return array_filter([
            'topic' => mb_substr($class->title, 0, 200),
            'type' => 2,
            'start_time' => $class->starts_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'duration' => max(1, (int) $class->starts_at->diffInMinutes($class->ends_at)),
            'timezone' => $tz,
            'agenda' => $class->description ? mb_substr($class->description, 0, 2000) : null,
            'settings' => ['join_before_host' => false, 'waiting_room' => true, 'mute_upon_entry' => true, 'approval_type' => 2, 'auto_recording' => 'none'],
        ], fn ($v) => $v !== null);
    }

    protected function api(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout((int) config('online_classes.http_timeout', 20));
    }

    protected function url(string $path): string
    {
        return rtrim(config('online_classes.providers.zoom.api'), '/').$path;
    }

    protected function accessToken(): string
    {
        $expires = (int) $this->integration->token('expires_at', 0);
        if ($this->integration->token('access_token') && $expires > time() + 60) {
            return $this->integration->token('access_token');
        }

        foreach (['account_id', 'client_id', 'client_secret'] as $key) {
            if (! $this->integration->setting($key)) {
                throw new MeetingException(__('Zoom is not configured yet: :field is missing.', ['field' => $key]));
            }
        }

        $response = Http::asForm()->acceptJson()->timeout((int) config('online_classes.http_timeout', 20))
            ->withBasicAuth($this->integration->setting('client_id'), $this->integration->setting('client_secret'))
            ->post(config('online_classes.providers.zoom.oauth'), ['grant_type' => 'account_credentials', 'account_id' => $this->integration->setting('account_id')]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new MeetingException(__('Zoom rejected the credentials').': '.$this->reason($response));
        }

        $this->integration->putTokens(['access_token' => $response->json('access_token'), 'expires_at' => time() + (int) $response->json('expires_in', 3600)]);

        return $response->json('access_token');
    }

    protected function json(Response $response, string $action): array
    {
        if ($response->status() === 401) {
            $this->integration->putTokens(['access_token' => null, 'expires_at' => 0]);   // force a fresh token next time
        }
        if (! $response->successful()) {
            throw new MeetingException(__('Could not :action', ['action' => $action]).': '.$this->reason($response));
        }

        return (array) $response->json();
    }

    protected function reason(Response $response): string
    {
        $message = $response->json('message') ?? $response->json('reason') ?? $response->json('error_description') ?? $response->json('error');

        return mb_substr(is_string($message) ? $message : 'HTTP '.$response->status(), 0, 200);
    }
}
