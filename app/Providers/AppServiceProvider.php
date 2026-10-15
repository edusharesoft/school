<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\RolePolicy;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One context per request / per queued job (reset automatically between them).
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Part 7 — slow query log (off unless PERF_SLOW_QUERY_MS / config('monitor.slow_query_ms') is above 0).
        if (($slow = (int) config('monitor.slow_query_ms')) > 0) {
            \Illuminate\Support\Facades\DB::listen(function ($query) use ($slow) {
                if ($query->time >= $slow) {
                    \Illuminate\Support\Facades\Log::warning('Slow query', ['ms' => round($query->time, 1), 'sql' => $query->sql, 'connection' => $query->connectionName]);
                    $key = 'monitor.slow.'.now()->format('YmdH');
                    \Illuminate\Support\Facades\Cache::add($key, 0, 7200);
                    \Illuminate\Support\Facades\Cache::increment($key);
                }
            });
        }

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Gate::policy(Role::class, RolePolicy::class);

        foreach ([
            \App\Models\Academic\AcademicYear::class, \App\Models\Academic\AcademicSession::class, \App\Models\Academic\Term::class,
            \App\Models\Academic\SchoolClass::class, \App\Models\Academic\Section::class, \App\Models\Academic\StudyGroup::class,
            \App\Models\Academic\Subject::class, \App\Models\Academic\ClassSubject::class, \App\Models\Academic\Room::class,
        ] as $model) {
            Gate::policy($model, \App\Policies\AcademicStructurePolicy::class);
        }
        Gate::policy(\App\Models\Website\Page::class, \App\Policies\WebsitePolicy::class);
        Gate::policy(\App\Models\Website\Post::class, \App\Policies\WebsitePolicy::class);
        Gate::policy(\App\Models\Students\Student::class, \App\Policies\StudentPolicy::class);
        Gate::policy(\App\Models\Students\Guardian::class, \App\Policies\GuardianPolicy::class);
        Gate::policy(\App\Models\HR\Employee::class, \App\Policies\EmployeePolicy::class);
        Gate::policy(\App\Models\HR\LeaveApplication::class, \App\Policies\LeaveApplicationPolicy::class);

        // Short, stable morph names for attendance corrections (other models keep their class name).
        Relation::morphMap([
            'student_attendance' => \App\Models\Attendance\StudentAttendance::class,
            'staff_attendance' => \App\Models\Attendance\StaffAttendance::class,
            'fee_invoice' => \App\Models\Finance\FeeInvoice::class,
            'fee_payment' => \App\Models\Finance\FeePayment::class,
            'fee_refund' => \App\Models\Finance\FeeRefund::class,
            'fee_allocation' => \App\Models\Finance\FeeAllocation::class,
            'expense' => \App\Models\Finance\Expense::class,
            'payroll_run' => \App\Models\Finance\PayrollRun::class,
            'notice' => \App\Models\Communication\Notice::class,
            'attendance_alert' => \App\Models\Attendance\AttendanceAlert::class,
            'exam_result' => \App\Models\Exams\ExamResult::class,
            'admission_application' => \App\Models\Admissions\AdmissionApplication::class,
            'student' => \App\Models\Students\Student::class,
            'assignment' => \App\Models\Learning\Assignment::class,
            'assignment_submission' => \App\Models\Learning\AssignmentSubmission::class,
            'online_class' => \App\Models\Learning\OnlineClass::class,
            'quiz' => \App\Models\Learning\Quiz::class,
            'quiz_attempt' => \App\Models\Learning\QuizAttempt::class,
            'goods_receipt' => \App\Models\Inventory\GoodsReceipt::class,
            'supplier_payment' => \App\Models\Inventory\SupplierPayment::class,
            'stock_issue' => \App\Models\Inventory\StockIssue::class,
            'stock_adjustment' => \App\Models\Inventory\StockAdjustment::class,
            'asset' => \App\Models\Assets\Asset::class,
            'asset_depreciation_run' => \App\Models\Assets\DepreciationRun::class,
            'asset_disposal' => \App\Models\Assets\Disposal::class,
        ]);

        // May the user take the register of this section? (class teacher of it, or back-office user with the permission)
        Gate::define('attendance.mark-section', fn (User $user, \App\Models\Academic\Section $section) => tenant()->isBranch()
            && $section->branch_id === tenant()->branchId()
            && app(\App\Services\Attendance\StudentAttendanceService::class)->canMarkSection($user, $section));

        // Super Admin passes every ability. Tenant isolation is still enforced by scopes/middleware.
        Gate::before(function (User $user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $identifier = strtolower((string) $request->input('login', $request->input('email', '')));

            return [
                Limit::perMinute(5)->by($identifier.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('handoff', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Public admission pages (no login): submissions and status / certificate lookups per IP.
        RateLimiter::for('admission-submit', fn (Request $request) => Limit::perMinute(10)->by('adm-submit|'.$request->ip()));
        RateLimiter::for('public-lookup', fn (Request $request) => Limit::perMinute(30)->by('lookup|'.$request->ip()));

        // Attendance devices push in bursts; keyed by the (hashed) device key, falling back to the IP.
        RateLimiter::for('device', fn (Request $request) => Limit::perMinute(60)
            ->by($request->header('X-Device-Key') ? hash('sha256', (string) $request->header('X-Device-Key')) : $request->ip()));
    }
}
