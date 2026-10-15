<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(TenantContext $tenant): View
    {
        return view('auth.login', [
            'branch' => $tenant->branch(),
            'isCentral' => $tenant->isCentral(),
        ]);
    }

    public function store(LoginRequest $request, TenantContext $tenant): RedirectResponse
    {
        $user = $request->authenticate();   // throws ValidationException on bad credentials / no tenant access

        if ($user->hasTwoFactor()) {
            // Password was right, but nobody is signed in until the second step is done.
            $request->session()->regenerate();
            $request->session()->put(['2fa.user' => $user->id, '2fa.remember' => $request->boolean('remember'), '2fa.tenant' => $tenant->key(),
                '2fa.until' => time() + 60 * (int) config('security.two_factor.challenge_minutes', 10)]);

            return redirect()->route('two-factor.challenge');
        }

        return self::completeLogin($request, $user, $request->boolean('remember'), $tenant);
    }

    public static function completeLogin(Request $request, User $user, bool $remember, TenantContext $tenant): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('tenant_key', $tenant->key());

        return redirect()->intended(HomeRedirectController::homeFor($user, $tenant));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Shared rule used by web login and API token issue. */
    public static function userMayEnterTenant(User $user, TenantContext $tenant): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($tenant->isCentral()) {
            return $user->canUseCentral();
        }

        return $tenant->isBranch() && $user->canAccessBranch($tenant->branch());
    }
}
