<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HomeRedirectController;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\HandoffService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Exchanges a single-use handoff token for a session on this host (branch switching). */
class HandoffController extends Controller
{
    public function __invoke(Request $request, string $token, HandoffService $handoff, TenantContext $tenant, AuditLogger $audit): RedirectResponse
    {
        $user = $handoff->consume($token, $tenant->key());

        if (! $user || ! LoginController::userMayEnterTenant($user, $tenant)) {
            return redirect()->route('login')->withErrors(['login' => __('This sign-in link is invalid or has expired.')]);
        }

        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('tenant_key', $tenant->key());

        $audit->log('auth.handoff.used', $user, userId: $user->getKey());

        return redirect()->to(HomeRedirectController::homeFor($user, $tenant));
    }
}
