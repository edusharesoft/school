<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Security\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Account security page: two-factor authentication set-up, recovery codes and switching it off. */
class SecurityController extends Controller
{
    public function __construct(protected TotpService $totp, protected AuditLogger $audit) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $pending = $request->session()->get('2fa.setup_secret');

        return view('account.security', [
            'user' => $user, 'pendingSecret' => $pending,
            'uri' => $pending ? $this->totp->uri($pending, $user->email) : null,
            'recoveryCodes' => $request->session()->pull('2fa.new_codes'),
            'codesLeft' => count((array) $user->two_factor_recovery_codes),
        ]);
    }

    /** Step 1: make a secret (kept in the session until confirmed). */
    public function start(Request $request): RedirectResponse
    {
        abort_if($request->user()->hasTwoFactor(), 404);
        $request->session()->put('2fa.setup_secret', $this->totp->generateSecret());

        return redirect()->route('account.security');
    }

    /** Step 2: the user types the first code from the app. */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        $secret = $request->session()->get('2fa.setup_secret');
        abort_if($user->hasTwoFactor() || ! $secret, 404);
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $step = $this->totp->verify($secret, (string) $request->input('code'));
        if ($step === null) {
            throw ValidationException::withMessages(['code' => __('That code is not correct. Check the time on your phone and try again.')]);
        }
        [$plain, $hashes] = $this->totp->recoveryCodes();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $hashes, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $request->session()->forget('2fa.setup_secret');
        $request->session()->put('2fa.new_codes', $plain);
        $this->audit->log('auth.2fa.enabled', $user, userId: $user->id);

        return redirect()->route('account.security')->with('success', __('Two-factor authentication is on. Save your recovery codes now.'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('2fa.setup_secret');

        return redirect()->route('account.security');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 404);
        $this->guardPassword($request);
        [$plain, $hashes] = $this->totp->recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $hashes])->save();
        $request->session()->put('2fa.new_codes', $plain);
        $this->audit->log('auth.2fa.codes_regenerated', $user, userId: $user->id);

        return redirect()->route('account.security')->with('success', __('New recovery codes created. The old ones no longer work.'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 404);
        if ($user->requiresTwoFactor()) {
            return back()->with('error', __('Your role requires two-factor authentication, so it cannot be switched off.'));
        }
        $this->guardPassword($request);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        $this->audit->log('auth.2fa.disabled', $user, userId: $user->id);

        return redirect()->route('account.security')->with('success', __('Two-factor authentication is off.'));
    }

    protected function guardPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! $this->totp->passwordOk($request->user(), (string) $request->input('password'))) {
            throw ValidationException::withMessages(['password' => __('The password is not correct.')]);
        }
    }
}
