<?php

use App\Exceptions\CrossTenantViolation;
use App\Exceptions\TenantContextMissing;
use App\Http\Middleware\EnsurePortal;
use App\Http\Middleware\EnsureTenantAccess;
use App\Http\Middleware\RequireBranch;
use App\Http\Middleware\RequireCentral;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Tenant resolution must happen before anything reads tenant data.
        $middleware->web(prepend: [ResolveTenant::class], append: [SetLocale::class, \App\Http\Middleware\EnforceSubscription::class]);
        $middleware->api(prepend: [ResolveTenant::class]);

        $middleware->alias([
            'tenant.branch' => RequireBranch::class,
            'tenant.central' => RequireCentral::class,
            'tenant.access' => EnsureTenantAccess::class,
            'portal' => EnsurePortal::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Host guards must run before authentication: a branch-only URL on the central domain
        // (or vice versa) is a 404 for everyone, not a redirect to the login page.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: RequireBranch::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: RequireCentral::class,
        );

        // Narrow the central context to the user's institution BEFORE route-model binding runs,
        // so bound tenant-owned models are resolved through the correct scope.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: EnsureTenantAccess::class,
        );

        // Provider webhooks are verified by signature, not by a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'online-payments/*/return/*', 'online-payments/*/ipn']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home.redirect'));

        // Behind a load balancer / reverse proxy in production.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([CrossTenantViolation::class]);
        // Gateway secrets typed into the settings form are never flashed back to the session on a validation error.
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'sslcommerz.store_passwd', 'bkash.password', 'bkash.app_secret',
            'nagad.merchant_private_key', 'nagad.nagad_public_key', 'shurjopay.password', 'aamarpay.signature_key']);

        // Consistent JSON envelope for the API: { success, message, errors? }
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $message, $errors] = match (true) {
                $e instanceof ValidationException => [422, __('The given data was invalid.'), $e->errors()],
                $e instanceof AuthenticationException => [401, __('Unauthenticated.'), null],
                $e instanceof AuthorizationException, $e instanceof UnauthorizedException => [403, __('This action is unauthorized.'), null],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, __('Resource not found.'), null],
                $e instanceof TenantContextMissing => [400, __('Tenant could not be determined.'), null],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: __('Request failed.'), null],
                default => [500, config('app.debug') ? $e->getMessage() : __('Server error.'), null],
            };

            return response()->json(array_filter([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], fn ($v) => $v !== null), $status);
        });
    })->create();
