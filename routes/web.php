<?php

use App\Http\Controllers\Auth\HandoffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
| Every request first passes ResolveTenant (web group). Routes below are split:
|   - shared    : any host (login, logout, locale, handoff)
|   - central   : only on TENANCY_CENTRAL_DOMAINS  (routes/central.php)
|   - branch    : only on a branch host             (routes/branch.php, routes/portal.php)
| Central routes are registered first and bound to the central domain, so on a
| central host they win; on branch hosts the RequireBranch middleware guards the rest.
*/

// ---------------------------------------------------------------- shared
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/two-factor', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:login')->name('two-factor.verify');
    Route::post('/two-factor/cancel', [TwoFactorChallengeController::class, 'cancel'])->name('two-factor.cancel');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:login')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:login')->name('password.update');
});

Route::middleware(['auth', 'tenant.access'])->prefix('account')->name('account.')->group(function () {
    Route::get('/security', [SecurityController::class, 'show'])->name('security');
    Route::post('/security/start', [SecurityController::class, 'start'])->name('security.start');
    Route::post('/security/confirm', [SecurityController::class, 'confirm'])->middleware('throttle:login')->name('security.confirm');
    Route::post('/security/cancel', [SecurityController::class, 'cancel'])->name('security.cancel');
    Route::post('/security/recovery-codes', [SecurityController::class, 'regenerate'])->middleware('throttle:login')->name('security.codes');
    Route::delete('/security', [SecurityController::class, 'disable'])->middleware('throttle:login')->name('security.disable');
});

// Uptime monitors: GET /health/status?token=HEALTH_TOKEN → 200 (ok / warn) or 503 (fail). 404 until HEALTH_TOKEN is set.
Route::get('/health/status', function (\Illuminate\Http\Request $request, \App\Services\Monitoring\HealthService $health) {
    $token = (string) config('monitor.token');
    abort_if($token === '' || ! hash_equals($token, (string) $request->query('token')), 404);
    $result = $health->run();

    return response()->json(['status' => $result['status'], 'at' => $result['at'], 'checks' => collect($result['checks'])->pluck('status', 'key')], $result['status'] === 'fail' ? 503 : 200);
})->middleware('throttle:60,1')->name('health.status');

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/locale/{locale}', LocaleController::class)->whereIn('locale', ['en', 'bn'])->name('locale.switch');
Route::get('/auth/handoff/{token}', HandoffController::class)->middleware('throttle:handoff')
    ->where('token', '[A-Za-z0-9]{64}')->name('auth.handoff');
Route::get('/home', HomeRedirectController::class)->middleware(['auth', 'tenant.access'])->name('home.redirect');

// ---------------------------------------------------------------- central domain
Route::domain(config('tenancy.central_domains')[0] ?? 'localhost')
    ->middleware('tenant.central')
    ->group(base_path('routes/central.php'));

// Additional central aliases (e.g. 127.0.0.1) reuse the same controllers without names.
foreach (array_slice(config('tenancy.central_domains'), 1) as $alias) {
    Route::domain($alias)->middleware('tenant.central')->group(function () use ($alias) {
        Route::get('/', fn () => redirect()->away(
            request()->getScheme().'://'.(config('tenancy.central_domains')[0] ?? 'localhost')
            .(in_array(request()->getPort(), [80, 443], true) ? '' : ':'.request()->getPort())
        ));
    });
}

// ---------------------------------------------------------------- branch hosts
Route::middleware('tenant.branch')->group(function () {
    require base_path('routes/branch.php');
    require base_path('routes/portal.php');
});
