<?php

namespace App\Http\Controllers\Branch\Learning;

use App\Http\Controllers\Controller;
use App\Models\Learning\OnlineClassIntegration;
use App\Services\Learning\Meetings\GoogleMeetProvider;
use App\Services\Learning\Meetings\MeetingException;
use App\Services\Learning\Meetings\MeetingProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Branch-level Zoom / Google Meet connection (permissions integrations.view / integrations.configure).
 * Secrets are write-only: the form shows whether a secret is saved, never its value; leaving it blank keeps it.
 */
class OnlineClassIntegrationController extends Controller
{
    public function __construct(protected MeetingProviders $providers) {}

    public function index(): View
    {
        $this->authorize('integrations.view');
        $rows = [];
        foreach (config('online_classes.providers') as $key => $def) {
            $integration = $this->providers->integration($key);
            $rows[$key] = [
                'def' => $def, 'integration' => $integration,
                'secretsSet' => collect($def['fields'])->filter(fn ($f) => ! empty($f['secret']))->keys()->filter(fn ($k) => filled($integration?->setting($k)))->all(),
                'connected' => $key === 'google_meet' ? (bool) $integration?->token('refresh_token') : (bool) $integration?->setting('client_secret'),
            ];
        }

        return view('branch.learning.integrations', ['rows' => $rows, 'defaultRedirect' => $this->defaultRedirect()]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $this->authorize('integrations.configure');
        $def = $this->definition($provider);
        $rules = ['is_enabled' => ['nullable', 'boolean']];
        foreach ($def['fields'] as $key => $field) {
            $rules[$key] = $field['type'] === 'url' ? ['nullable', 'url:http,https', 'max:500'] : ['nullable', 'string', 'max:500'];
        }
        $data = $request->validate($rules);

        $integration = OnlineClassIntegration::query()->firstOrNew(['provider' => $provider]);
        $config = $integration->config ?? [];
        foreach ($def['fields'] as $key => $field) {
            $value = trim((string) ($data[$key] ?? ''));
            if (! empty($field['secret'])) {
                if ($value !== '') {
                    $config[$key] = $value;      // blank = keep the saved secret
                }

                continue;
            }
            if ($value === '') {
                unset($config[$key]);
            } else {
                $config[$key] = $value;
            }
        }
        // Changing the OAuth client invalidates tokens issued to the old one.
        $credentialsChanged = $integration->exists && (($integration->config['client_id'] ?? null) !== ($config['client_id'] ?? null) || ($integration->config['account_id'] ?? null) !== ($config['account_id'] ?? null));
        $integration->config = $config;
        if ($credentialsChanged) {
            $integration->tokens = null;
            $integration->connected_at = null;
            $integration->account_label = null;
        }
        $integration->is_enabled = $request->boolean('is_enabled');
        $integration->save();

        return back()->with('success', __(':provider settings saved.', ['provider' => $def['label']]));
    }

    /** Verify the saved credentials with a harmless API call. */
    public function test(string $provider): RedirectResponse
    {
        $this->authorize('integrations.configure');
        $def = $this->definition($provider);
        $integration = $this->providers->integration($provider);
        abort_unless($integration, 404);

        try {
            $this->providers->make($integration)->check();
            $integration->forceFill(['last_checked_at' => now(), 'last_error' => null, 'connected_at' => $integration->connected_at ?? now()])->save();

            return back()->with('success', __(':provider connection works.', ['provider' => $def['label']]));
        } catch (MeetingException $e) {
            $integration->forceFill(['last_checked_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 500)])->save();

            return back()->with('warning', $e->getMessage());
        }
    }

    public function googleRedirect(Request $request): RedirectResponse
    {
        $this->authorize('integrations.configure');
        $integration = $this->providers->integration('google_meet');
        if (! $integration || ! $integration->setting('client_id') || ! $integration->setting('client_secret')) {
            return back()->with('warning', __('Save the Google client ID and secret first.'));
        }
        $state = Str::random(40);
        $request->session()->put('google_meet_state', $state);

        return redirect()->away((new GoogleMeetProvider($integration))->authorizationUrl($this->redirectUri($integration), $state));
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $this->authorize('integrations.configure');
        $expected = (string) $request->session()->pull('google_meet_state');
        $back = redirect()->route('branch.learning.integrations.index');
        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state'))) {
            return $back->with('warning', __('The Google sign-in could not be verified. Please try again.'));
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return $back->with('warning', __('Google sign-in was cancelled.'));
        }
        $integration = $this->providers->integration('google_meet');
        abort_unless($integration, 404);

        try {
            (new GoogleMeetProvider($integration))->connect((string) $request->query('code'), $this->redirectUri($integration));
        } catch (MeetingException $e) {
            return $back->with('warning', $e->getMessage());
        }

        return $back->with('success', __('Google account connected.'));
    }

    public function disconnect(string $provider): RedirectResponse
    {
        $this->authorize('integrations.configure');
        $this->definition($provider);
        $integration = $this->providers->integration($provider);
        abort_unless($integration, 404);
        $integration->forceFill(['tokens' => null, 'connected_at' => null, 'account_label' => null, 'is_enabled' => false])->save();

        return back()->with('success', __('Disconnected. Classes already scheduled keep their links.'));
    }

    protected function definition(string $provider): array
    {
        $def = config("online_classes.providers.$provider");
        abort_unless($def, 404);

        return $def;
    }

    protected function redirectUri(OnlineClassIntegration $integration): string
    {
        return $integration->setting('redirect_uri') ?: $this->defaultRedirect();
    }

    protected function defaultRedirect(): string
    {
        return route('branch.learning.integrations.google.callback');
    }
}
