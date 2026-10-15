<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\TotpService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Second step of the sign-in for users who switched on two-factor authentication. */
class TwoFactorChallengeController extends Controller
{
    public function __construct(protected TotpService $totp, protected TenantContext $tenant) {}

    public function create(Request $request): View|RedirectResponse
    {
        return $this->pending($request) ? view('auth.two-factor', ['isCentral' => $this->tenant->isCentral()]) : redirect()->route('login');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['login' => __('Your sign-in expired. Please sign in again.')]);
        }
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $key = '2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Try again in :s seconds.', ['s' => RateLimiter::availableIn($key)])]);
        }
        if (! $this->totp->check($user, (string) $request->input('code'))) {
            RateLimiter::hit($key, 60);
            $audit->log('auth.2fa.failed', $user, userId: $user->id);
            throw ValidationException::withMessages(['code' => __('That code is not correct.')]);
        }
        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('2fa.remember', false);
        $request->session()->forget(['2fa.user', '2fa.until', '2fa.tenant']);

        return LoginController::completeLogin($request, $user, $remember, $this->tenant);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['2fa.user', '2fa.until', '2fa.tenant', '2fa.remember']);

        return redirect()->route('login');
    }

    protected function pending(Request $request): ?User
    {
        $id = $request->session()->get('2fa.user');
        if (! $id || $request->session()->get('2fa.until', 0) < time() || $request->session()->get('2fa.tenant') !== $this->tenant->key()) {
            return null;
        }
        $user = User::query()->find($id);

        return $user && $user->hasTwoFactor() && LoginController::userMayEnterTenant($user, $this->tenant) ? $user : null;
    }
}
