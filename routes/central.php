<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Central\AcademicYearController;
use App\Http\Controllers\Central\AuditLogController;
use App\Http\Controllers\Central\BranchController;
use App\Http\Controllers\Central\BranchDomainController;
use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\InstitutionController;
use App\Http\Controllers\Central\RoleController;
use App\Http\Controllers\Central\Saas\PlanController;
use App\Http\Controllers\Central\Saas\SubscriptionController;
use App\Http\Controllers\Central\SettingsController;
use App\Http\Controllers\Central\SystemController;
use App\Http\Controllers\Central\UserController;
use App\Http\Controllers\Central\Website\PageController as WebsitePageAdminController;
use App\Http\Controllers\Central\Website\PostController as WebsitePostAdminController;
use App\Http\Controllers\Central\Website\SettingsController as WebsiteSettingsController;
use App\Http\Controllers\Central\Website\SubscriberController as WebsiteSubscriberController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PageController as PublicPageController;
use App\Http\Controllers\Public\PostController as PublicPostController;
use App\Http\Controllers\Public\WebsiteController;
use App\Http\Controllers\SwitchBranchController;
use Illuminate\Support\Facades\Route;

/*
| Central domain: public website + Super Admin area (prefix TENANCY_CENTRAL_PATH).
| All admin routes: auth → tenant.access (central permission, institution narrowing) → permissions.
*/

Route::get('/', [WebsiteController::class, 'home'])->name('website.home');
Route::get('/branches', [WebsiteController::class, 'branches'])->name('website.branches');
Route::get('/page/{slug}', [PublicPageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('website.page');
Route::get('/news', [PublicPostController::class, 'news'])->name('website.news');
Route::get('/news/{slug}', [PublicPostController::class, 'showNews'])->where('slug', '[a-z0-9-]+')->name('website.post');
Route::get('/notices', [PublicPostController::class, 'notices'])->name('website.notices');
Route::get('/notices/{slug}', [PublicPostController::class, 'showNotice'])->where('slug', '[a-z0-9-]+')->name('website.notice');
Route::post('/newsletter', [NewsletterController::class, 'subscribe'])->middleware('throttle:6,1')->name('website.newsletter');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->middleware('throttle:20,1')->name('website.newsletter.unsubscribe');

// Self-service institution registration (only when enabled in Settings; otherwise 404).
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:login')->name('register.store');
    Route::get('/register/check-subdomain', [RegisterController::class, 'checkSubdomain'])->middleware('throttle:60,1')->name('register.check-subdomain');
});

Route::prefix(config('tenancy.central_path', 'super-admin'))
    ->middleware(['auth', 'tenant.access'])
    ->name('central.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->middleware('permission:dashboard.central')->name('dashboard');
        Route::get('/dashboard/data', [DashboardController::class, 'data'])->middleware('permission:dashboard.central')->name('dashboard.data');
        Route::get('/dashboard/export', [DashboardController::class, 'export'])->middleware('permission:dashboard.export')->name('dashboard.export');

        Route::resource('institutions', InstitutionController::class)->except(['show', 'destroy']);

        // Part 7 — backups & system health (platform owner only)
        Route::get('system/health', [SystemController::class, 'health'])->name('system.health');
        Route::get('system/backups', [SystemController::class, 'backups'])->name('backups.index');
        Route::post('system/backups', [SystemController::class, 'run'])->name('backups.run');
        Route::post('system/backups/{backup}/verify', [SystemController::class, 'verify'])->name('backups.verify');
        Route::get('system/backups/{backup}/download', [SystemController::class, 'download'])->name('backups.download');
        Route::delete('system/backups/{backup}', [SystemController::class, 'destroy'])->name('backups.destroy');

        // Part 7 — SaaS subscriptions (platform owner: subscriptions.*)
        Route::resource('plans', PlanController::class)->except(['show']);
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('subscriptions/{institution}', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::put('subscriptions/{institution}', [SubscriptionController::class, 'update'])->name('subscriptions.update');
        Route::post('subscriptions/{institution}/payments', [SubscriptionController::class, 'payment'])->name('subscriptions.payment');
        Route::post('subscriptions/{institution}/{action}', [SubscriptionController::class, 'action'])->whereIn('action', ['suspend', 'reactivate', 'cancel'])->name('subscriptions.action');

        Route::resource('branches', BranchController::class)->except(['destroy']);
        Route::patch('branches/{branch}/status', [BranchController::class, 'toggleStatus'])->name('branches.status');
        Route::post('branches/{branch}/domains', [BranchDomainController::class, 'store'])->name('branches.domains.store');
        Route::delete('branches/{branch}/domains/{domain}', [BranchDomainController::class, 'destroy'])
            ->scopeBindings()->name('branches.domains.destroy');

        Route::resource('academic-years', AcademicYearController::class)->except(['show', 'destroy']);

        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::post('users/{user}/two-factor/reset', [UserController::class, 'resetTwoFactor'])->name('users.two-factor.reset');
        Route::get('staff-assignments', [\App\Http\Controllers\Central\StaffAssignmentController::class, 'index'])->name('staff-assignments.index');
        Route::post('staff-assignments', [\App\Http\Controllers\Central\StaffAssignmentController::class, 'store'])->name('staff-assignments.store');
        Route::post('staff-assignments/{assignment}/end', [\App\Http\Controllers\Central\StaffAssignmentController::class, 'end'])->name('staff-assignments.end');
        Route::resource('roles', RoleController::class)->except(['show']);

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        // Public website CMS
        Route::prefix('website')->name('website.')->group(function () {
            Route::get('settings', [WebsiteSettingsController::class, 'edit'])->name('settings');
            Route::put('settings', [WebsiteSettingsController::class, 'update'])->name('settings.update');
            Route::resource('pages', WebsitePageAdminController::class)->except(['show']);
            Route::resource('posts', WebsitePostAdminController::class)->except(['show']);
            Route::get('subscribers', [WebsiteSubscriberController::class, 'index'])->name('subscribers');
            Route::get('subscribers/export', [WebsiteSubscriberController::class, 'export'])->name('subscribers.export');
        });

        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-logs.view')->name('audit-logs.index');
        Route::get('finance', [\App\Http\Controllers\Central\FinanceReportController::class, 'index'])->name('finance.index');
        Route::get('inventory', [\App\Http\Controllers\Central\InventoryReportController::class, 'index'])->name('inventory.index');
        Route::get('assets', [\App\Http\Controllers\Central\AssetReportController::class, 'index'])->name('assets.index');

        // Reports centre (Part 6e) — branch comparison reports with CSV / Excel / PDF and background exports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Reports\ReportCentreController::class, 'index'])->name('index');
            Route::get('exports', [\App\Http\Controllers\Reports\ExportRunController::class, 'index'])->name('exports.index');
            Route::get('exports/{run}/download', [\App\Http\Controllers\Reports\ExportRunController::class, 'download'])->name('exports.download');
            Route::delete('exports/{run}', [\App\Http\Controllers\Reports\ExportRunController::class, 'destroy'])->name('exports.destroy');
            Route::get('r/{report}', [\App\Http\Controllers\Reports\ReportCentreController::class, 'show'])->where('report', '[a-z0-9.\-]+')->name('show');
            Route::get('r/{report}/export', [\App\Http\Controllers\Reports\ReportCentreController::class, 'export'])->where('report', '[a-z0-9.\-]+')->name('export');
            Route::post('r/{report}/queue', [\App\Http\Controllers\Reports\ReportCentreController::class, 'queue'])->where('report', '[a-z0-9.\-]+')->name('queue');
        });
        Route::get('notices', [\App\Http\Controllers\Central\InstitutionNoticeController::class, 'index'])->name('notices.index');
        Route::get('notices/create', [\App\Http\Controllers\Central\InstitutionNoticeController::class, 'create'])->name('notices.create');
        Route::post('notices', [\App\Http\Controllers\Central\InstitutionNoticeController::class, 'store'])->name('notices.store');

        Route::get('switch-branch', [SwitchBranchController::class, 'index'])->name('switch-branch');
        Route::post('switch-branch/{branch}', [SwitchBranchController::class, 'switch'])->name('switch-branch.go');
    });
