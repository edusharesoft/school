<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifies the tenant from the Host header and populates TenantContext.
 * Runs first in the web and api middleware groups.
 */
class ResolveTenant
{
    public function __construct(
        protected TenantResolver $resolver,
        protected TenantContext $tenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $result = $this->resolver->resolveHost($request->getHost());

        if ($result === TenantResolver::REDIRECT_APEX) {
            $apex = config('tenancy.base_domain');
            $port = in_array($request->getPort(), [80, 443], true) ? '' : ':'.$request->getPort();

            return redirect()->away($request->getScheme().'://'.$apex.$port.$request->getRequestUri(), 301);
        }

        if ($result === TenantResolver::CENTRAL) {
            $this->tenant->setCentral(null);
            View::share('currentBranch', null);

            return $next($request);
        }

        if ($result === TenantResolver::MISSING) {
            abort(404, __('This branch address is not registered.'));
        }

        $branch = $this->resolver->findBranch((int) $result);

        if (! $branch) {
            $this->resolver->forget($request->getHost());
            abort(404, __('This branch address is not registered.'));
        }

        if (! $branch->institution?->isActive()) {
            abort(403, __('This institution account is suspended. Please contact the administration.'));
        }

        if (! $branch->isActive()) {
            abort(503, __('This branch is currently unavailable.'));
        }

        $this->tenant->setBranch($branch);
        View::share('currentBranch', $branch);

        return $next($request);
    }
}
