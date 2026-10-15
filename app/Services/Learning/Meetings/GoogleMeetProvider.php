<?php

namespace App\Services\Learning\Meetings;

use App\Models\Learning\OnlineClass;
use App\Models\Learning\OnlineClassIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Google Meet through the Google Calendar API: each class is a calendar event with a Meet conference attached.
 * The branch connects one Google account once (OAuth authorisation code, offline access); the refresh token is
 * stored encrypted and the short-lived access token is renewed automatically.
 */
class GoogleMeetProvider implements MeetingProvider
{
    public function __construct(protected OnlineClassIntegration $integration) {}

    // ---- OAuth (connect screen) -------------------------------------------------------------------------------

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return config('online_classes.providers.google_meet.auth_url').'?'.http_build_query([
            'client_id' => $this->integration->setting('client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => config('online_classes.providers.google_meet.scope').' openid email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** Exchange the authorisation code for tokens and remember them. @throws MeetingException */
    public function connect(string $code, string $redirectUri): void
    {
        $response = Http::asForm()->acceptJson()->timeout((int) config('online_classes.http_timeout', 20))
            ->post(config('online_classes.providers.google_meet.token_url'), [
                'code' => $code, 'client_id' => $this->integration->setting('client_id'), 'client_secret' => $this->integration->setting('client_secret'),
                'redirect_uri' => $redirectUri, 'grant_type' => 'authorization_code',
            ]);
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new MeetingException(__('Google rejected the authorisation').': '.$this->reason($response));
        }
        if (! $response->json('refresh_token') && ! $this->integration->token('refresh_token')) {
            throw new MeetingException(__('Google did not return a refresh token. Remove the app from your Google account permissions and connect again.'));
        }

        $this->integration->putTokens(array_filter([
            'access_token' => $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_at' => time() + (int) $response->json('expires_in', 3600),
        ]));
        $this->integration->forceFill(['account_label' => $this->emailFromIdToken($response->json('id_token')), 'connected_at' => now(), 'last_error' => null])->save();
    }

    public function isConnected(): bool
    {
        return (bool) $this->integration->token('refresh_token');
    }

    // ---- MeetingProvider --------------------------------------------------------------------------------------

    public function create(OnlineClass $class): MeetingDetails
    {
        $body = $this->payload($class) + ['conferenceData' => ['createRequest' => [
            'requestId' => (string) Str::uuid(), 'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
        ]]];
        $event = $this->json($this->api()->post($this->url('/calendars/'.$this->calendar().'/events?conferenceDataVersion=1&sendUpdates=none'), $body), 'create the Google Meet event');

        // Conference creation can finish a moment after the insert; read the event once more before giving up.
        if (empty($event['hangoutLink']) && ! empty($event['id'])) {
            $event = $this->json($this->api()->get($this->url('/calendars/'.$this->calendar().'/events/'.rawurlencode($event['id']).'?conferenceDataVersion=1')), 'read the Google Meet event');
        }
        if (empty($event['id']) || empty($event['hangoutLink'])) {
            throw new MeetingException(__('Google did not return a Meet link. Check that Google Meet is enabled for the connected account.'));
        }

        return new MeetingDetails($event['id'], $event['hangoutLink'], $event['htmlLink'] ?? null, $this->meetCode($event['hangoutLink']));
    }

    public function update(OnlineClass $class): void
    {
        $this->json($this->api()->patch($this->url('/calendars/'.$this->calendar().'/events/'.rawurlencode($class->external_id).'?sendUpdates=none'), $this->payload($class)), 'update the Google Meet event');
    }

    public function cancel(OnlineClass $class): void
    {
        $response = $this->api()->delete($this->url('/calendars/'.$this->calendar().'/events/'.rawurlencode($class->external_id).'?sendUpdates=none'));
        if (in_array($response->status(), [404, 410], true)) {
            return;
        }
        $this->json($response, 'cancel the Google Meet event');
    }

    /** Meet recordings land in the organiser's Drive; the Calendar API cannot return them, so staff paste the link. */
    public function recording(OnlineClass $class): ?string
    {
        return null;
    }

    public function check(): void
    {
        $this->json($this->api()->get($this->url('/calendars/'.$this->calendar())), 'reach Google Calendar');
    }

    // ---- internals --------------------------------------------------------------------------------------------

    protected function payload(OnlineClass $class): array
    {
        $tz = config('app.timezone');

        return [
            'summary' => mb_substr($class->title, 0, 250),
            'description' => (string) $class->description,
            'start' => ['dateTime' => $class->starts_at->copy()->setTimezone($tz)->format('Y-m-d\TH:i:sP'), 'timeZone' => $tz],
            'end' => ['dateTime' => $class->ends_at->copy()->setTimezone($tz)->format('Y-m-d\TH:i:sP'), 'timeZone' => $tz],
        ];
    }

    protected function calendar(): string
    {
        return rawurlencode($this->integration->setting('calendar_id') ?: 'primary');
    }

    protected function url(string $path): string
    {
        return rtrim(config('online_classes.providers.google_meet.api'), '/').$path;
    }

    protected function api(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout((int) config('online_classes.http_timeout', 20));
    }

    protected function accessToken(): string
    {
        if ($this->integration->token('access_token') && (int) $this->integration->token('expires_at', 0) > time() + 60) {
            return $this->integration->token('access_token');
        }
        if (! $this->isConnected()) {
            throw new MeetingException(__('Google Meet is not connected. Use “Connect Google account” first.'));
        }

        $response = Http::asForm()->acceptJson()->timeout((int) config('online_classes.http_timeout', 20))
            ->post(config('online_classes.providers.google_meet.token_url'), [
                'client_id' => $this->integration->setting('client_id'), 'client_secret' => $this->integration->setting('client_secret'),
                'refresh_token' => $this->integration->token('refresh_token'), 'grant_type' => 'refresh_token',
            ]);
        if (! $response->successful() || ! $response->json('access_token')) {
            if ($response->json('error') === 'invalid_grant') {
                $this->integration->putTokens(['access_token' => null, 'refresh_token' => null, 'expires_at' => 0]);   // revoked: connect again
            }
            throw new MeetingException(__('Google rejected the saved connection. Connect the Google account again').': '.$this->reason($response));
        }

        $this->integration->putTokens(['access_token' => $response->json('access_token'), 'expires_at' => time() + (int) $response->json('expires_in', 3600)]);

        return $response->json('access_token');
    }

    protected function json(Response $response, string $action): array
    {
        if ($response->status() === 401) {
            $this->integration->putTokens(['access_token' => null, 'expires_at' => 0]);
        }
        if (! $response->successful()) {
            throw new MeetingException(__('Could not :action', ['action' => $action]).': '.$this->reason($response));
        }

        return (array) $response->json();
    }

    protected function reason(Response $response): string
    {
        $error = $response->json('error');
        $message = is_array($error) ? ($error['message'] ?? null) : ($response->json('error_description') ?? $error);

        return mb_substr(is_string($message) ? $message : 'HTTP '.$response->status(), 0, 200);
    }

    protected function meetCode(string $link): ?string
    {
        return ltrim((string) parse_url($link, PHP_URL_PATH), '/') ?: null;
    }

    /** The id_token is only read for the account label (e-mail); it is not trusted for anything else. */
    protected function emailFromIdToken(?string $idToken): ?string
    {
        $parts = explode('.', (string) $idToken);
        if (count($parts) !== 3) {
            return null;
        }
        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($claims) && is_string($claims['email'] ?? null) ? mb_substr($claims['email'], 0, 191) : null;
    }
}
