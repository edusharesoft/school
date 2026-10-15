<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Locale: session choice → user preference → branch default → app default. */
class SetLocale
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['en', 'bn']);

        $candidates = [
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->user()?->locale,
            $this->tenant->branch()?->locale,
            config('app.locale'),
        ];

        foreach ($candidates as $locale) {
            if ($locale && in_array($locale, $supported, true)) {
                app()->setLocale($locale);
                Carbon::setLocale($locale);
                break;
            }
        }

        return $next($request);
    }
}
