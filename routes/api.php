<?php

use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AttendanceDeviceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\FeeController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Support\Facades\Route;

/*
| REST API v1. Tenant = request host (same rules as web). Tokens are bound to the tenant
| they were issued on (Sanctum ability "tenant:<key>").
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    // Attendance devices authenticate with their own key (X-Device-Key), never with a user token.
    Route::post('attendance/punches', AttendanceDeviceController::class)->middleware(['tenant.branch', 'throttle:device'])->name('attendance.punches');

    Route::middleware(['auth:sanctum', 'tenant.access', \App\Http\Middleware\EnforceSubscription::class, 'throttle:api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');

        Route::middleware('tenant.branch')->group(function () {
            Route::apiResource('departments', DepartmentController::class);
            Route::apiResource('students', StudentController::class)->only(['index', 'show']);
            Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
            Route::get('admissions/{campaign}/applications', [AdmissionController::class, 'applications'])->name('admissions.applications');
            Route::get('teachers', [EmployeeController::class, 'index'])->name('teachers.index');
            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
            Route::get('attendance/students', [AttendanceController::class, 'index'])->name('attendance.students.index');
            Route::post('attendance/students', [AttendanceController::class, 'store'])->name('attendance.students.store');
            Route::get('exams', [ExamController::class, 'index'])->name('exams.index');
            Route::get('exams/{exam}/results', [ExamController::class, 'results'])->name('exams.results');
            Route::get('my/results', [ExamController::class, 'mine'])->name('my.results');
            Route::get('fees/invoices', [FeeController::class, 'invoices'])->name('fees.invoices');
            Route::get('payments', [FeeController::class, 'payments'])->name('payments.index');
            Route::get('my/fees', [FeeController::class, 'mine'])->name('my.fees');
            Route::get('my/assignments', [\App\Http\Controllers\Api\V1\LearningController::class, 'assignments'])->name('my.assignments');
            Route::get('my/online-classes', [\App\Http\Controllers\Api\V1\LearningController::class, 'onlineClasses'])->name('my.online-classes');
            Route::get('my/materials', [\App\Http\Controllers\Api\V1\LearningController::class, 'materials'])->name('my.materials');
            Route::get('my/quizzes', [\App\Http\Controllers\Api\V1\LearningController::class, 'quizzes'])->name('my.quizzes');
            Route::get('my/learning', [\App\Http\Controllers\Api\V1\LearningController::class, 'progress'])->name('my.learning');
            Route::get('my/facilities', [\App\Http\Controllers\Api\V1\FacilitiesController::class, 'mine'])->name('my.facilities');
            Route::get('my/library', [\App\Http\Controllers\Api\V1\LibraryController::class, 'mine'])->name('my.library');
            Route::get('my/online-payments/gateways', [\App\Http\Controllers\Api\V1\OnlinePaymentController::class, 'gateways'])->name('my.online-payments.gateways');
            Route::post('my/online-payments', [\App\Http\Controllers\Api\V1\OnlinePaymentController::class, 'store'])->middleware('throttle:10,1')->name('my.online-payments.store');
            Route::post('my/online-payments/{tran}/claim', [\App\Http\Controllers\Api\V1\OnlinePaymentController::class, 'claim'])->where('tran', '[A-Z0-9]+')->middleware('throttle:10,1')->name('my.online-payments.claim');
            Route::get('my/online-payments/{tran}', [\App\Http\Controllers\Api\V1\OnlinePaymentController::class, 'show'])->where('tran', '[A-Z0-9]+')->name('my.online-payments.show');
            Route::get('notices', [\App\Http\Controllers\Api\V1\NoticeController::class, 'index'])->name('notices.index');
            Route::get('notices/{notice}', [\App\Http\Controllers\Api\V1\NoticeController::class, 'show'])->name('notices.show');
            Route::get('notifications', [\App\Http\Controllers\Api\V1\NoticeController::class, 'notifications'])->name('notifications.index');
            Route::post('notifications/{message}/read', [\App\Http\Controllers\Api\V1\NoticeController::class, 'read'])->name('notifications.read');
        });
    });
});
