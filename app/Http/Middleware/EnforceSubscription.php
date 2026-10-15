<?php

namespace App\Http\Middleware;

use App\Services\Saas\SubscriptionService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An institution whose trial / paid period (and grace period) has run out, or that the platform owner suspended,
 * keeps read access — nothing is hidden or deleted — but every change is refused until the subscription is renewed.
 */
class EnforceSubscription
{
    public function __construct(protected TenantContext $tenant, protected SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! ($user = $request->user()) || $user->isPlatformOwner() || $request->routeIs('logout', 'locale.switch', 'account.*', 'login*')) {
            return $next($request);
        }
        $subscription = $this->subscriptions->of($this->tenant->institution());
        if (! $subscription || $subscription->isWritable()) {
            return $next($request);
        }

        $message = $subscription->phase() === 'suspended'
            ? __('This institution’s subscription is suspended. You can view data but not change it. Please contact the platform administrator.')
            : __('The subscription has expired. You can view data but not change it until it is renewed.');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], 402);
        }

        return redirect()->back(fallback: '/')->with('error', $message);
    }
}
