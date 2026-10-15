<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('portal:teacher') or 'portal:admin'. */
class EnsurePortal
{
    public function handle(Request $request, Closure $next, string ...$portals): Response
    {
        $user = $request->user();

        abort_unless($user && in_array($user->portal(), $portals, true), 403);

        return $next($request);
    }
}
