<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Re-validates on EVERY authenticated request that the user may act in the current tenant,
 * and that the session / API token was issued for this tenant. Also narrows the central
 * context to the user's own institution.
 */
class EnsureTenantAccess
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $next($request); // 'auth' middleware decides about guests
        }

        if (! $user->isActive()) {
            return $this->deny($request, __('Your account is not active.'));
        }

        if ($this->tenant->isCentral()) {
            if (! $user->canUseCentral()) {
                return $this->deny($request, __('You do not have access to the central administration.'));
            }
            if (! $user->isPlatformOwner()) {
                $this->tenant->setInstitution($user->institution);
            }
        } elseif ($this->tenant->isBranch()) {
            if (! $user->canAccessBranch($this->tenant->branch())) {
                return $this->deny($request, __('You do not have access to this branch.'));
            }
        } else {
            return $this->deny($request, __('Unknown tenant.'));
        }

        // Roles that must use two-factor are held at the Account security page until it is set up (sessions only).
        if ($user->requiresTwoFactor() && ! $user->hasTwoFactor() && $request->hasSession() && $request->isMethodSafe() && ! $request->expectsJson()
            && ! $request->routeIs('account.*', 'logout', 'locale.switch')) {
            return redirect()->route('account.security')->with('warning', __('Please set up two-factor authentication to continue.'));
        }

        // Session / token must have been issued for this tenant.
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            if (! $token->can('tenant:'.$this->tenant->key())) {
                return $this->deny($request, __('This API token is not valid for this branch.'));
            }
        } elseif ($request->hasSession() && $request->session()->get('tenant_key') !== $this->tenant->key()) {
            return $this->deny($request, __('Your session does not belong to this branch. Please sign in again.'));
        }

        return $next($request);
    }

    protected function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['login' => $message]);
    }
}
