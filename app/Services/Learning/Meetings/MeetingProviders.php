<?php

namespace App\Services\Learning\Meetings;

use App\Models\Learning\OnlineClassIntegration;

/** Resolves the provider adapter for the current branch. Credentials come from the branch's integration row. */
class MeetingProviders
{
    public function integration(string $provider): ?OnlineClassIntegration
    {
        return OnlineClassIntegration::query()->where('provider', $provider)->first();
    }

    public function isReady(string $provider): bool
    {
        $integration = $this->integration($provider);
        if (! $integration || ! $integration->is_enabled) {
            return false;
        }

        return $provider === 'google_meet' ? $this->make($integration)->isConnected() : (bool) $integration->setting('client_secret');
    }

    /** Providers a teacher may pick right now: the manual link always, Zoom / Google Meet once they are configured. @return string[] */
    public function available(): array
    {
        return array_values(array_filter(['zoom', 'google_meet', 'manual'], fn ($p) => $p === 'manual' || $this->isReady($p)));
    }

    /** @throws MeetingException */
    public function for(string $provider): MeetingProvider
    {
        $integration = $this->integration($provider);
        if (! $integration || ! $integration->is_enabled) {
            throw new MeetingException(__(':provider is not enabled for this branch.', ['provider' => config("online_classes.providers.$provider.label", $provider)]));
        }

        return $this->make($integration);
    }

    public function make(OnlineClassIntegration $integration): ZoomProvider|GoogleMeetProvider
    {
        return match ($integration->provider) {
            'zoom' => new ZoomProvider($integration),
            'google_meet' => new GoogleMeetProvider($integration),
            default => throw new MeetingException(__('Unknown provider.')),
        };
    }
}
