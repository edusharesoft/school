<?php

use App\Http\Controllers\Branch\Academic\AcademicSessionController;
use App\Http\Controllers\Branch\Academic\ClassSubjectController;
use App\Http\Controllers\Branch\Academic\PeriodController;
use App\Http\Controllers\Branch\Academic\RoomController;
use App\Http\Controllers\Branch\Academic\SchoolClassController;
use App\Http\Controllers\Branch\Academic\SectionController;
use App\Http\Controllers\Branch\Academic\StudyGroupController;
use App\Http\Controllers\Branch\Academic\SubjectController;
use App\Http\Controllers\Branch\Academic\TimetableController;
use App\Http\Controllers\Branch\Admissions\ApplicationController;
use App\Http\Controllers\Branch\Admissions\CampaignController;
use App\Http\Controllers\Branch\Admissions\MeritController;
use App\Http\Controllers\Branch\Attendance\AttendanceCorrectionController;
use App\Http\Controllers\Branch\Attendance\AttendanceReportController;
use App\Http\Controllers\Branch\Attendance\AttendanceScanController;
use App\Http\Controllers\Branch\Attendance\AttendanceSettingsController;
use App\Http\Controllers\Branch\Attendance\StaffAttendanceController;
use App\Http\Controllers\Branch\Attendance\StudentAttendanceController;
use App\Http\Controllers\Branch\AuditLogController;
use App\Http\Controllers\Branch\Communication\MessageController;
use App\Http\Controllers\Branch\Communication\NoticeController;
use App\Http\Controllers\Branch\Communication\NotificationSettingsController;
use App\Http\Controllers\Branch\Communication\OptOutController;
use App\Http\Controllers\Branch\Communication\TemplateController;
use App\Http\Controllers\Communication\InboxController;
use App\Http\Controllers\Communication\MessagePreferencesController;
use App\Http\Controllers\Communication\NoticeBoardController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Controllers\Branch\Finance\OnlinePaymentController as OnlinePaymentAdminController;
use App\Http\Controllers\Payments\GatewayCallbackController;
use App\Http\Controllers\Payments\OnlinePaymentController;
use App\Http\Controllers\Branch\Finance\AccountController;
use App\Http\Controllers\Branch\Finance\AccountingReportController;
use App\Http\Controllers\Branch\Finance\ExpenseController;
use App\Http\Controllers\Branch\Finance\JournalController;
use App\Http\Controllers\Branch\Finance\PayrollController;
use App\Http\Controllers\Branch\Finance\VendorController;
use App\Http\Controllers\Branch\Finance\FeeHeadController;
use App\Http\Controllers\Branch\Finance\FeeInvoiceController;
use App\Http\Controllers\Branch\Finance\FeePaymentController;
use App\Http\Controllers\Branch\Finance\FeeReportController;
use App\Http\Controllers\Branch\Finance\FeeStructureController;
use App\Http\Controllers\Branch\Finance\FinanceSettingsController;
use App\Http\Controllers\Branch\Finance\StudentFeeController;
use App\Http\Controllers\Branch\HR\DesignationController;
use App\Http\Controllers\Branch\HR\EmployeeController;
use App\Http\Controllers\Branch\HR\EmployeeDocumentController;
use App\Http\Controllers\Branch\HR\LeaveApplicationController;
use App\Http\Controllers\Branch\HR\LeaveTypeController;
use App\Http\Controllers\Branch\Students\CertificateController;
use App\Http\Controllers\Branch\Students\PortalAccountController;
use App\Http\Controllers\Branch\Students\PromotionController;
use App\Http\Controllers\Branch\Students\TransferController;
use App\Http\Controllers\Branch\Students\StudentController;
use App\Http\Controllers\Branch\Students\StudentDocumentController;
use App\Http\Controllers\Branch\Students\StudentGuardianController;
use App\Http\Controllers\Branch\Students\StudentImportExportController;
use App\Http\Controllers\Branch\DashboardController;
use App\Http\Controllers\Branch\DepartmentController;
use App\Http\Controllers\Branch\Exams\ExaminationController;
use App\Http\Controllers\Branch\Exams\GradingScaleController;
use App\Http\Controllers\Branch\Exams\MarkController;
use App\Http\Controllers\Branch\Exams\MarkCorrectionController;
use App\Http\Controllers\Branch\Exams\ResultController;
use App\Http\Controllers\Branch\SettingsController;
use App\Http\Controllers\Branch\Website\PageController as WebsitePageController;
use App\Http\Controllers\Branch\Website\PostController as WebsitePostController;
use App\Http\Controllers\Branch\Website\SettingsController as WebsiteSettingsController;
use App\Http\Controllers\Public\AdmissionPortalController;
use App\Http\Controllers\Public\BranchSiteController;
use App\Http\Controllers\Public\CertificateVerifyController;
use App\Http\Controllers\Public\ResultLookupController;
use App\Http\Controllers\SwitchBranchController;
use Illuminate\Support\Facades\Route;

/*
| Branch host (e.g. branch3.schooldomain.com). Loaded inside the 'tenant.branch' group.
*/

// ---------------------------------------------------------------- the branch's own public website
Route::get('/', [BranchSiteController::class, 'home'])->name('branch.home');
Route::get('/page/{slug}', [BranchSiteController::class, 'page'])->where('slug', '[a-z0-9-]+')->name('site.page');
Route::get('/news', [BranchSiteController::class, 'news'])->name('site.news');
Route::get('/news/{slug}', [BranchSiteController::class, 'showNews'])->where('slug', '[a-z0-9-]+')->name('site.post');
// (/notices is the signed-in notice board, so the public notices live under /public-notices)
Route::get('/public-notices', [BranchSiteController::class, 'notices'])->name('site.notices');
Route::get('/public-notices/{slug}', [BranchSiteController::class, 'showNotice'])->where('slug', '[a-z0-9-]+')->name('site.notice');
Route::get('/message/{n}', [BranchSiteController::class, 'message'])->whereNumber('n')->name('site.message');
Route::post('/newsletter', [BranchSiteController::class, 'newsletter'])->middleware('throttle:6,1')->name('site.newsletter');

// ---------------------------------------------------------------- public pages of the branch (no login) — Part 3B-2
Route::prefix('admission')->name('admission.')->group(function () {
    Route::get('/', [AdmissionPortalController::class, 'index'])->name('index');
    Route::get('/done', [AdmissionPortalController::class, 'done'])->name('done');
    Route::get('/status', [AdmissionPortalController::class, 'status'])->middleware('throttle:public-lookup')->name('status');
    Route::get('/{campaign}/apply', [AdmissionPortalController::class, 'form'])->name('form');
    Route::post('/{campaign}/apply', [AdmissionPortalController::class, 'submit'])->middleware('throttle:admission-submit')->name('submit');
    Route::get('/{campaign}/result', [AdmissionPortalController::class, 'merit'])->name('merit');
});
Route::get('/certificates/verify', CertificateVerifyController::class)->middleware('throttle:public-lookup')->name('certificates.verify');
Route::get('/id/verify', \App\Http\Controllers\Public\IdCardVerifyController::class)->middleware('throttle:public-lookup')->name('idcards.verify');
Route::get('/results', [ResultLookupController::class, 'index'])->name('results.index');
Route::post('/results', [ResultLookupController::class, 'lookup'])->middleware('throttle:public-lookup')->name('results.lookup');

Route::middleware(['auth', 'tenant.access', 'portal:admin'])->name('branch.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.branch')->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->middleware('permission:dashboard.branch')->name('dashboard.data');

    Route::resource('departments', DepartmentController::class)->except(['show']);

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit-logs.view')->name('audit-logs.index');

    Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    // ---------------------------------------------------------------- the branch's public website (pages, news, site settings)
    Route::prefix('website')->name('website.')->group(function () {
        Route::get('settings', [WebsiteSettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [WebsiteSettingsController::class, 'update'])->name('settings.update');
        Route::post('pages/defaults', [WebsitePageController::class, 'defaults'])->name('pages.defaults');
        Route::resource('pages', WebsitePageController::class)->except(['show']);
        Route::resource('posts', WebsitePostController::class)->except(['show']);
    });

    // ---------------------------------------------------------------- academic structure (Part 3A)
    Route::prefix('academics')->name('academics.')->group(function () {
        Route::resource('sessions', AcademicSessionController::class)->except(['show', 'destroy']);
        Route::post('sessions/{session}/terms', [AcademicSessionController::class, 'storeTerm'])->name('sessions.terms.store');
        Route::delete('sessions/{session}/terms/{term}', [AcademicSessionController::class, 'destroyTerm'])->name('sessions.terms.destroy');

        Route::resource('classes', SchoolClassController::class)->except(['show']);
        Route::get('classes/{class}/subjects', [ClassSubjectController::class, 'index'])->name('classes.subjects.index');
        Route::post('classes/{class}/subjects', [ClassSubjectController::class, 'store'])->name('classes.subjects.store');
        Route::put('classes/{class}/subjects/{classSubject}', [ClassSubjectController::class, 'update'])->name('classes.subjects.update');
        Route::delete('classes/{class}/subjects/{classSubject}', [ClassSubjectController::class, 'destroy'])->name('classes.subjects.destroy');

        Route::resource('sections', SectionController::class)->except(['index', 'show']);
        Route::resource('subjects', SubjectController::class)->except(['show']);
        Route::resource('groups', StudyGroupController::class)->except(['show']);
        Route::resource('rooms', RoomController::class)->except(['show']);

        Route::get('periods', [PeriodController::class, 'index'])->name('periods.index');
        Route::post('periods', [PeriodController::class, 'store'])->name('periods.store');
        Route::put('periods/{period}', [PeriodController::class, 'update'])->name('periods.update');
        Route::delete('periods/{period}', [PeriodController::class, 'destroy'])->name('periods.destroy');

        Route::get('timetable', [TimetableController::class, 'index'])->name('timetable.index');
        Route::post('timetable', [TimetableController::class, 'store'])->name('timetable.store');
        Route::get('timetable/teachers', [TimetableController::class, 'teacher'])->name('timetable.teacher');
        Route::get('timetable/rooms', [TimetableController::class, 'room'])->name('timetable.room');
    });

    // ---------------------------------------------------------------- students (Part 3A)
    Route::get('students/import', [StudentImportExportController::class, 'form'])->name('students.import');
    Route::post('students/import', [StudentImportExportController::class, 'import'])->name('students.import.store');
    Route::get('students/import/template', [StudentImportExportController::class, 'template'])->name('students.import.template');
    Route::get('students/export', [StudentImportExportController::class, 'export'])->name('students.export');
    Route::get('students/promotion', [PromotionController::class, 'create'])->name('students.promotion');
    Route::post('students/promotion', [PromotionController::class, 'store'])->name('students.promotion.store');

    Route::resource('students', StudentController::class)->except(['destroy']);
    Route::patch('students/{student}/status', [StudentController::class, 'changeStatus'])->name('students.status');
    Route::post('students/{student}/readmit', [StudentController::class, 'readmit'])->name('students.readmit');
    Route::post('students/{student}/transfer', [TransferController::class, 'store'])->name('students.transfer');
    Route::post('students/{student}/certificates', [CertificateController::class, 'store'])->name('students.certificates.store');
    Route::post('students/{student}/portal/student', [PortalAccountController::class, 'storeStudent'])->name('students.portal.student');
    Route::post('students/{student}/portal/guardians/{guardian}', [PortalAccountController::class, 'storeGuardian'])->name('students.portal.guardian');
    Route::post('students/{student}/portal/accounts/{account}/password', [PortalAccountController::class, 'resetPassword'])->name('students.portal.password');
    Route::get('students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');
    Route::post('students/{student}/guardians', [StudentGuardianController::class, 'store'])->name('students.guardians.store');
    Route::delete('students/{student}/guardians/{guardian}', [StudentGuardianController::class, 'destroy'])->name('students.guardians.destroy');
    Route::post('students/{student}/documents', [StudentDocumentController::class, 'store'])->name('students.documents.store');
    Route::get('students/{student}/documents/{document}', [StudentDocumentController::class, 'download'])->name('students.documents.download');
    Route::delete('students/{student}/documents/{document}', [StudentDocumentController::class, 'destroy'])->name('students.documents.destroy');

    // ---------------------------------------------------------------- documents: ID cards, document look (Part 6d)
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('settings', [\App\Http\Controllers\Branch\Documents\DocumentSettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [\App\Http\Controllers\Branch\Documents\DocumentSettingsController::class, 'update'])->name('settings.update');
    });
    Route::prefix('id-cards')->name('idcards.')->group(function () {
        $c = \App\Http\Controllers\Branch\Documents\IdCardController::class;
        Route::get('/', [$c, 'index'])->name('index');
        Route::post('print', [$c, 'print'])->name('print');
        Route::get('register', [$c, 'register'])->name('register');
        Route::get('students/{student}/pdf', [$c, 'student'])->name('student');
        Route::get('employees/{employee}/pdf', [$c, 'employee'])->name('employee');
        Route::get('{card}/pdf', [$c, 'reprint'])->name('reprint');
        Route::post('{card}/revoke', [$c, 'revoke'])->name('revoke');
        Route::post('{card}/reissue', [$c, 'reissue'])->name('reissue');
    });

    // ---------------------------------------------------------------- admissions, transfers, certificates (Part 3B-2)
    Route::prefix('admissions')->name('admissions.')->group(function () {
        Route::resource('campaigns', CampaignController::class)->except(['show', 'destroy']);
        Route::get('campaigns/{campaign}/marks', [MeritController::class, 'marks'])->name('merit.marks');
        Route::post('campaigns/{campaign}/marks', [MeritController::class, 'storeMarks'])->name('merit.marks.store');
        Route::get('campaigns/{campaign}/merit', [MeritController::class, 'show'])->name('merit.show');
        Route::post('campaigns/{campaign}/merit', [MeritController::class, 'generate'])->name('merit.generate');
        Route::post('campaigns/{campaign}/merit/promote', [MeritController::class, 'promote'])->name('merit.promote');
        Route::post('campaigns/{campaign}/merit/publish', [MeritController::class, 'publish'])->name('merit.publish');

        Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/create', [ApplicationController::class, 'create'])->name('applications.create');
        Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
        Route::patch('applications/{application}/status', [ApplicationController::class, 'updateStatus'])->name('applications.status');
        Route::post('applications/{application}/fee', [ApplicationController::class, 'recordFee'])->name('applications.fee');
        Route::post('applications/{application}/admit', [ApplicationController::class, 'admit'])->name('applications.admit');
        Route::get('applications/{application}/photo', [ApplicationController::class, 'photo'])->name('applications.photo');
        Route::get('applications/{application}/documents/{document}', [ApplicationController::class, 'document'])->name('applications.documents.download');
    });

    // Examinations (grading scales before the {exam} resource so "grading" is not taken as an exam id)
    Route::prefix('exams/grading')->name('exams.grading.')->group(function () {
        Route::get('/', [GradingScaleController::class, 'index'])->name('index');
        Route::get('create', [GradingScaleController::class, 'create'])->name('create');
        Route::post('/', [GradingScaleController::class, 'store'])->name('store');
        Route::get('{grading}/edit', [GradingScaleController::class, 'edit'])->name('edit');
        Route::put('{grading}', [GradingScaleController::class, 'update'])->name('update');
    });
    Route::get('exams/marks', [MarkController::class, 'index'])->name('exams.marks.index');
    Route::get('exams/corrections', [MarkCorrectionController::class, 'index'])->name('exams.corrections.index');
    Route::post('exams/corrections', [MarkCorrectionController::class, 'store'])->name('exams.corrections.store');
    Route::post('exams/corrections/{correction}/approve', [MarkCorrectionController::class, 'approve'])->name('exams.corrections.approve');
    Route::post('exams/corrections/{correction}/reject', [MarkCorrectionController::class, 'reject'])->name('exams.corrections.reject');
    Route::resource('exams', ExaminationController::class);
    Route::get('exams/{exam}/marks/{paper}/{section}', [MarkController::class, 'sheet'])->name('exams.marks.sheet');
    Route::post('exams/{exam}/marks/{paper}/{section}', [MarkController::class, 'store'])->name('exams.marks.store');
    Route::get('exams/{exam}/results', [ResultController::class, 'index'])->name('exams.results.index');
    Route::post('exams/{exam}/results/process', [ResultController::class, 'process'])->name('exams.results.process');
    Route::post('exams/{exam}/results/approve', [ResultController::class, 'approve'])->name('exams.results.approve');
    Route::post('exams/{exam}/results/publish', [ResultController::class, 'publish'])->name('exams.results.publish');
    Route::post('exams/{exam}/results/unpublish', [ResultController::class, 'unpublish'])->name('exams.results.unpublish');
    Route::post('exams/{exam}/results/reopen', [ResultController::class, 'reopen'])->name('exams.results.reopen');
    Route::get('exams/{exam}/results/tabulation', [ResultController::class, 'tabulation'])->name('exams.results.tabulation');
    Route::get('exams/{exam}/results/marksheets', [ResultController::class, 'marksheets'])->name('exams.results.marksheets');
    Route::get('exams/{exam}/results/marksheets/pdf', [ResultController::class, 'marksheetsPdf'])->name('exams.results.marksheets.pdf');
    Route::get('exams/{exam}/results/{result}', [ResultController::class, 'marksheet'])->name('exams.results.marksheet');
    Route::get('exams/{exam}/results/{result}/pdf', [ResultController::class, 'marksheetPdf'])->name('exams.results.marksheet.pdf');
    Route::get('exams/{exam}/admit-cards', [ResultController::class, 'admitCards'])->name('exams.admit-cards');
    Route::get('exams/{exam}/admit-cards/pdf', [ResultController::class, 'admitCardsPdf'])->name('exams.admit-cards.pdf');
    Route::get('exams/{exam}/seat-plan', [ResultController::class, 'seatPlan'])->name('exams.seat-plan');
    Route::post('exams/{exam}/classes', [ExaminationController::class, 'addClass'])->name('exams.classes.add');
    Route::put('exams/{exam}/papers', [ExaminationController::class, 'updateClass'])->name('exams.classes.update');
    Route::delete('exams/{exam}/papers/{paper}', [ExaminationController::class, 'removePaper'])->name('exams.papers.destroy');
    Route::post('exams/{exam}/status', [ExaminationController::class, 'changeStatus'])->name('exams.status');
    Route::get('exams/{exam}/routine', [ExaminationController::class, 'routine'])->name('exams.routine');

    // Fees & accounts (Part 4)
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('students', [StudentFeeController::class, 'search'])->name('students.search');
        Route::get('students/{student}', [StudentFeeController::class, 'show'])->name('students.show');
        Route::post('students/{student}/adjustments', [StudentFeeController::class, 'storeAdjustment'])->name('students.adjustments.store');
        Route::post('students/{student}/adjustments/{adjustment}/toggle', [StudentFeeController::class, 'toggleAdjustment'])->name('students.adjustments.toggle');
        Route::post('students/{student}/payments', [FeePaymentController::class, 'store'])->name('payments.store');
        Route::post('students/{student}/refunds', [FeePaymentController::class, 'refund'])->name('refunds.store');

        Route::get('invoices', [FeeInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [FeeInvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [FeeInvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/generate', [FeeInvoiceController::class, 'generateForm'])->name('invoices.generate');
        Route::post('invoices/generate', [FeeInvoiceController::class, 'generate'])->name('invoices.generate.run');
        Route::post('invoices/late-fees', [FeeInvoiceController::class, 'lateFees'])->name('invoices.late-fees');
        Route::get('invoices/{invoice}', [FeeInvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/cancel', [FeeInvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/waive', [FeeInvoiceController::class, 'waive'])->name('invoices.waive');

        Route::get('payments', [FeePaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [FeePaymentController::class, 'show'])->name('payments.show');
        Route::post('payments/{payment}/reverse', [FeePaymentController::class, 'reverse'])->name('payments.reverse');
        Route::get('refunds/{refund}', [FeePaymentController::class, 'refundShow'])->name('refunds.show');

        Route::get('heads', [FeeHeadController::class, 'index'])->name('heads.index');
        Route::post('heads', [FeeHeadController::class, 'store'])->name('heads.store');
        Route::put('heads/{head}', [FeeHeadController::class, 'update'])->name('heads.update');
        Route::get('structures', [FeeStructureController::class, 'index'])->name('structures.index');
        Route::post('structures', [FeeStructureController::class, 'store'])->name('structures.store');
        Route::post('structures/copy', [FeeStructureController::class, 'copy'])->name('structures.copy');

        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');

        Route::get('settings', [FinanceSettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [FinanceSettingsController::class, 'update'])->name('settings.update');

        Route::get('reports/collection', [FeeReportController::class, 'collection'])->name('reports.collection');
        Route::get('reports/dues', [FeeReportController::class, 'dues'])->name('reports.dues');
        Route::get('reports/discounts', [FeeReportController::class, 'discounts'])->name('reports.discounts');

        // Part 4-2 — expenses, payroll, vouchers, statements
        Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::post('vendors', [VendorController::class, 'store'])->name('vendors.store');
        Route::put('vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');

        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        Route::get('expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])->name('expenses.attachment');
        Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
        Route::post('expenses/{expense}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
        Route::post('expenses/{expense}/reverse', [ExpenseController::class, 'reverse'])->name('expenses.reverse');

        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll', [PayrollController::class, 'prepare'])->name('payroll.prepare');
        Route::get('payroll/salaries', [PayrollController::class, 'salaries'])->name('payroll.salaries');
        Route::post('payroll/salaries/{employee}', [PayrollController::class, 'saveSalary'])->name('payroll.salaries.save');
        Route::get('payroll/{run}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('payroll/{run}/slips', [PayrollController::class, 'slips'])->name('payroll.slips');
        Route::post('payroll/{run}/items/{item}', [PayrollController::class, 'adjust'])->name('payroll.adjust');
        Route::post('payroll/{run}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
        Route::post('payroll/{run}/pay', [PayrollController::class, 'pay'])->name('payroll.pay');
        Route::post('payroll/{run}/cancel', [PayrollController::class, 'cancel'])->name('payroll.cancel');

        Route::get('journal', [JournalController::class, 'index'])->name('journal.index');
        Route::get('journal/create', [JournalController::class, 'create'])->name('journal.create');
        Route::post('journal', [JournalController::class, 'store'])->name('journal.store');
        Route::get('journal/{entry}', [JournalController::class, 'show'])->name('journal.show');
        Route::post('journal/{entry}/reverse', [JournalController::class, 'reverse'])->name('journal.reverse');

        Route::get('reports/ledger', [AccountingReportController::class, 'ledger'])->name('reports.ledger');
        Route::get('reports/trial-balance', [AccountingReportController::class, 'trialBalance'])->name('reports.trial-balance');
        Route::get('reports/income-expenditure', [AccountingReportController::class, 'incomeExpenditure'])->name('reports.income-expenditure');
        Route::get('reports/balance-sheet', [AccountingReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('reports/cash-flow', [AccountingReportController::class, 'cashFlow'])->name('reports.cash-flow');
        Route::get('reports/budget', [AccountingReportController::class, 'budget'])->name('reports.budget');
        Route::post('reports/budget', [AccountingReportController::class, 'saveBudget'])->name('reports.budget.save');
        Route::get('reports/reconcile', [AccountingReportController::class, 'reconcile'])->name('reports.reconcile');
        Route::post('reports/reconcile', [AccountingReportController::class, 'saveReconcile'])->name('reports.reconcile.save');
        Route::get('years', [AccountingReportController::class, 'years'])->name('years.index');
        Route::post('years/{year}/toggle', [AccountingReportController::class, 'toggleYear'])->name('years.toggle');

        // Online payments (Part 5-2)
        Route::get('online-payments', [OnlinePaymentAdminController::class, 'index'])->name('online-payments.index');
        Route::get('online-payments/statement', [OnlinePaymentAdminController::class, 'statement'])->name('online-payments.statement');
        Route::post('online-payments/statement', [OnlinePaymentAdminController::class, 'matchStatement'])->middleware('throttle:20,1')->name('online-payments.statement.match');
        Route::get('online-payments/settings', [OnlinePaymentAdminController::class, 'settings'])->name('online-payments.settings');
        Route::put('online-payments/settings', [OnlinePaymentAdminController::class, 'saveSettings'])->name('online-payments.settings.save');
        Route::get('online-payments/{payment}', [OnlinePaymentAdminController::class, 'show'])->name('online-payments.show');
        Route::post('online-payments/{payment}/requery', [OnlinePaymentAdminController::class, 'requery'])->middleware('throttle:30,1')->name('online-payments.requery');
        Route::post('online-payments/{payment}/refund', [OnlinePaymentAdminController::class, 'refund'])->name('online-payments.refund');
        Route::post('online-payments/{payment}/review', [OnlinePaymentAdminController::class, 'review'])->name('online-payments.review');
    });

    // Library — catalogue, circulation desk, reservations, fines (Part 6a)
    // ---------------------------------------------------------------- reports centre (Part 6e): every module report with CSV / Excel / PDF and background exports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Reports\ReportCentreController::class, 'index'])->name('index');
        Route::get('exports', [\App\Http\Controllers\Reports\ExportRunController::class, 'index'])->name('exports.index');
        Route::get('exports/{run}/download', [\App\Http\Controllers\Reports\ExportRunController::class, 'download'])->name('exports.download');
        Route::delete('exports/{run}', [\App\Http\Controllers\Reports\ExportRunController::class, 'destroy'])->name('exports.destroy');
        Route::get('r/{report}', [\App\Http\Controllers\Reports\ReportCentreController::class, 'show'])->where('report', '[a-z0-9.\-]+')->name('show');
        Route::get('r/{report}/export', [\App\Http\Controllers\Reports\ReportCentreController::class, 'export'])->where('report', '[a-z0-9.\-]+')->name('export');
        Route::post('r/{report}/queue', [\App\Http\Controllers\Reports\ReportCentreController::class, 'queue'])->where('report', '[a-z0-9.\-]+')->name('queue');
    });

    Route::prefix('library')->name('library.')->group(function () {
        $lib = 'App\\Http\\Controllers\\Branch\\Library\\';
        Route::get('/', [$lib.'LibraryController', 'index'])->name('index');
        Route::get('desk', [$lib.'CirculationController', 'desk'])->name('desk');
        Route::get('desk/members', [$lib.'CirculationController', 'members'])->middleware('throttle:60,1')->name('desk.members');
        Route::post('desk/issue', [$lib.'CirculationController', 'issue'])->name('issue');
        Route::post('desk/return', [$lib.'CirculationController', 'return'])->name('return');
        Route::post('loans/{loan}/renew', [$lib.'CirculationController', 'renew'])->name('renew');
        Route::get('loans', [$lib.'CirculationController', 'loans'])->name('loans');
        Route::get('members/{type}/{id}', [$lib.'CirculationController', 'member'])->where(['type' => 'student|employee', 'id' => '[0-9]+'])->name('member');

        Route::get('books', [$lib.'BookController', 'index'])->name('books.index');
        Route::get('books/create', [$lib.'BookController', 'create'])->name('books.create');
        Route::post('books', [$lib.'BookController', 'store'])->name('books.store');
        Route::get('books/{book}', [$lib.'BookController', 'show'])->name('books.show');
        Route::get('books/{book}/edit', [$lib.'BookController', 'edit'])->name('books.edit');
        Route::put('books/{book}', [$lib.'BookController', 'update'])->name('books.update');
        Route::delete('books/{book}', [$lib.'BookController', 'destroy'])->name('books.destroy');
        Route::post('books/{book}/copies', [$lib.'BookController', 'addCopies'])->name('books.copies.store');
        Route::get('books/{book}/labels', [$lib.'BookController', 'labels'])->name('books.labels');
        Route::put('copies/{copy}/status', [$lib.'BookController', 'copyStatus'])->name('copies.status');

        Route::get('categories', [$lib.'CategoryController', 'index'])->name('categories.index');
        Route::post('categories', [$lib.'CategoryController', 'store'])->name('categories.store');
        Route::put('categories/{category}', [$lib.'CategoryController', 'update'])->name('categories.update');
        Route::delete('categories/{category}', [$lib.'CategoryController', 'destroy'])->name('categories.destroy');

        Route::get('reservations', [$lib.'ReservationController', 'index'])->name('reservations.index');
        Route::post('reservations', [$lib.'ReservationController', 'store'])->name('reservations.store');
        Route::post('reservations/{reservation}/cancel', [$lib.'ReservationController', 'cancel'])->name('reservations.cancel');

        Route::get('fines', [$lib.'FineController', 'index'])->name('fines.index');
        Route::post('fines', [$lib.'FineController', 'store'])->name('fines.store');
        Route::post('fines/{fine}/pay', [$lib.'FineController', 'pay'])->name('fines.pay');
        Route::post('fines/{fine}/waive', [$lib.'FineController', 'waive'])->name('fines.waive');
        Route::get('fines/{fine}/receipt', [$lib.'FineController', 'receipt'])->name('fines.receipt');

        Route::get('rules', [$lib.'SettingsController', 'edit'])->name('rules.edit');
        Route::put('rules', [$lib.'SettingsController', 'update'])->name('rules.update');
    });

    // Transport & hostel (Part 6b)
    Route::get('operations/students', \App\Http\Controllers\Branch\Operations\StudentLookupController::class)->middleware('throttle:90,1')->name('operations.students');

    Route::prefix('transport')->name('transport.')->group(function () {
        $c = 'App\\Http\\Controllers\\Branch\\Transport\\';
        Route::get('/', [$c.'TransportController', 'index'])->name('index');
        Route::get('fees', [$c.'TransportController', 'fees'])->name('fees');
        Route::post('fees', [$c.'TransportController', 'generate'])->name('fees.generate');

        Route::get('vehicles', [$c.'VehicleController', 'index'])->name('vehicles.index');
        Route::get('vehicles/create', [$c.'VehicleController', 'create'])->name('vehicles.create');
        Route::post('vehicles', [$c.'VehicleController', 'store'])->name('vehicles.store');
        Route::get('vehicles/{vehicle}', [$c.'VehicleController', 'show'])->name('vehicles.show');
        Route::get('vehicles/{vehicle}/edit', [$c.'VehicleController', 'edit'])->name('vehicles.edit');
        Route::put('vehicles/{vehicle}', [$c.'VehicleController', 'update'])->name('vehicles.update');
        Route::delete('vehicles/{vehicle}', [$c.'VehicleController', 'destroy'])->name('vehicles.destroy');
        Route::post('vehicles/{vehicle}/maintenance', [$c.'VehicleController', 'storeMaintenance'])->name('vehicles.maintenance.store');
        Route::delete('maintenance/{maintenance}', [$c.'VehicleController', 'destroyMaintenance'])->name('maintenance.destroy');
        Route::post('vehicles/{vehicle}/fuel', [$c.'VehicleController', 'storeFuel'])->name('vehicles.fuel.store');
        Route::delete('fuel/{fuel}', [$c.'VehicleController', 'destroyFuel'])->name('fuel.destroy');

        Route::get('drivers', [$c.'DriverController', 'index'])->name('drivers.index');
        Route::get('drivers/create', [$c.'DriverController', 'create'])->name('drivers.create');
        Route::post('drivers', [$c.'DriverController', 'store'])->name('drivers.store');
        Route::get('drivers/{driver}/edit', [$c.'DriverController', 'edit'])->name('drivers.edit');
        Route::put('drivers/{driver}', [$c.'DriverController', 'update'])->name('drivers.update');
        Route::delete('drivers/{driver}', [$c.'DriverController', 'destroy'])->name('drivers.destroy');
        Route::get('attendance', [$c.'DriverController', 'attendance'])->name('attendance');
        Route::post('attendance', [$c.'DriverController', 'saveAttendance'])->name('attendance.save');

        Route::get('routes', [$c.'RouteController', 'index'])->name('routes.index');
        Route::get('routes/create', [$c.'RouteController', 'create'])->name('routes.create');
        Route::post('routes', [$c.'RouteController', 'store'])->name('routes.store');
        Route::get('routes/{route}', [$c.'RouteController', 'show'])->name('routes.show');
        Route::get('routes/{route}/sheet', [$c.'RouteController', 'sheet'])->name('routes.sheet');
        Route::get('routes/{route}/edit', [$c.'RouteController', 'edit'])->name('routes.edit');
        Route::put('routes/{route}', [$c.'RouteController', 'update'])->name('routes.update');
        Route::delete('routes/{route}', [$c.'RouteController', 'destroy'])->name('routes.destroy');
        Route::post('routes/{route}/stops', [$c.'RouteController', 'storeStop'])->name('stops.store');
        Route::put('stops/{stop}', [$c.'RouteController', 'updateStop'])->name('stops.update');
        Route::delete('stops/{stop}', [$c.'RouteController', 'destroyStop'])->name('stops.destroy');

        Route::get('allocations', [$c.'TransportAllocationController', 'index'])->name('allocations.index');
        Route::post('allocations', [$c.'TransportAllocationController', 'store'])->name('allocations.store');
        Route::put('allocations/{allocation}', [$c.'TransportAllocationController', 'update'])->name('allocations.update');
        Route::post('allocations/{allocation}/transfer', [$c.'TransportAllocationController', 'transfer'])->name('allocations.transfer');
        Route::post('allocations/{allocation}/end', [$c.'TransportAllocationController', 'end'])->name('allocations.end');
    });

    Route::prefix('hostel')->name('hostel.')->group(function () {
        $c = 'App\\Http\\Controllers\\Branch\\Hostel\\';
        Route::get('/', [$c.'HostelController', 'index'])->name('index');
        Route::get('buildings/create', [$c.'HostelController', 'create'])->name('buildings.create');
        Route::post('buildings', [$c.'HostelController', 'store'])->name('buildings.store');
        Route::get('buildings/{hostel}/edit', [$c.'HostelController', 'edit'])->name('buildings.edit');
        Route::put('buildings/{hostel}', [$c.'HostelController', 'update'])->name('buildings.update');
        Route::delete('buildings/{hostel}', [$c.'HostelController', 'destroy'])->name('buildings.destroy');
        Route::get('fees', [$c.'HostelController', 'fees'])->name('fees');
        Route::post('fees', [$c.'HostelController', 'generate'])->name('fees.generate');

        Route::get('rooms', [$c.'RoomController', 'index'])->name('rooms.index');
        Route::get('rooms/create', [$c.'RoomController', 'create'])->name('rooms.create');
        Route::post('rooms', [$c.'RoomController', 'store'])->name('rooms.store');
        Route::get('rooms/{room}', [$c.'RoomController', 'show'])->name('rooms.show');
        Route::get('rooms/{room}/edit', [$c.'RoomController', 'edit'])->name('rooms.edit');
        Route::put('rooms/{room}', [$c.'RoomController', 'update'])->name('rooms.update');
        Route::delete('rooms/{room}', [$c.'RoomController', 'destroy'])->name('rooms.destroy');
        Route::post('rooms/{room}/beds', [$c.'RoomController', 'addBeds'])->name('rooms.beds.store');
        Route::post('beds/{bed}/toggle', [$c.'RoomController', 'toggleBed'])->name('beds.toggle');

        Route::get('allocations', [$c.'HostelAllocationController', 'index'])->name('allocations.index');
        Route::post('allocations', [$c.'HostelAllocationController', 'store'])->name('allocations.store');
        Route::post('allocations/{allocation}/transfer', [$c.'HostelAllocationController', 'transfer'])->name('allocations.transfer');
        Route::post('allocations/{allocation}/meal-plan', [$c.'HostelAllocationController', 'mealPlan'])->name('allocations.meal-plan');
        Route::post('allocations/{allocation}/vacate', [$c.'HostelAllocationController', 'vacate'])->name('allocations.vacate');

        Route::get('meal-plans', [$c.'MealPlanController', 'index'])->name('meal-plans.index');
        Route::post('meal-plans', [$c.'MealPlanController', 'store'])->name('meal-plans.store');
        Route::put('meal-plans/{plan}', [$c.'MealPlanController', 'update'])->name('meal-plans.update');
        Route::delete('meal-plans/{plan}', [$c.'MealPlanController', 'destroy'])->name('meal-plans.destroy');

        Route::get('attendance', [$c.'HostelAttendanceController', 'index'])->name('attendance');
        Route::post('attendance', [$c.'HostelAttendanceController', 'store'])->name('attendance.save');
        Route::get('visitors', [$c.'VisitorController', 'index'])->name('visitors.index');
        Route::post('visitors', [$c.'VisitorController', 'store'])->name('visitors.store');
        Route::post('visitors/{visitor}/checkout', [$c.'VisitorController', 'checkout'])->name('visitors.checkout');
        Route::get('maintenance', [$c.'HostelMaintenanceController', 'index'])->name('maintenance.index');
        Route::post('maintenance', [$c.'HostelMaintenanceController', 'store'])->name('maintenance.store');
        Route::put('maintenance/{maintenance}', [$c.'HostelMaintenanceController', 'update'])->name('maintenance.update');
    });

    // Inventory (Part 6c-1): items & stock, purchase requests → orders → goods receiving → supplier payments, issues, adjustments, audits
    Route::prefix('inventory')->name('inventory.')->group(function () {
        $c = 'App\\Http\\Controllers\\Branch\\Inventory\\';
        Route::get('/', [$c.'InventoryController', 'index'])->name('index');

        Route::get('categories', [$c.'CategoryController', 'index'])->name('categories.index');
        Route::post('categories', [$c.'CategoryController', 'store'])->name('categories.store');
        Route::put('categories/{category}', [$c.'CategoryController', 'update'])->name('categories.update');
        Route::delete('categories/{category}', [$c.'CategoryController', 'destroy'])->name('categories.destroy');

        Route::get('items', [$c.'ItemController', 'index'])->name('items.index');
        Route::get('items/create', [$c.'ItemController', 'create'])->name('items.create');
        Route::post('items', [$c.'ItemController', 'store'])->name('items.store');
        Route::get('items/{item}', [$c.'ItemController', 'show'])->name('items.show');
        Route::get('items/{item}/edit', [$c.'ItemController', 'edit'])->name('items.edit');
        Route::put('items/{item}', [$c.'ItemController', 'update'])->name('items.update');
        Route::delete('items/{item}', [$c.'ItemController', 'destroy'])->name('items.destroy');

        Route::get('suppliers', [$c.'SupplierController', 'index'])->name('suppliers.index');
        Route::post('suppliers', [$c.'SupplierController', 'store'])->name('suppliers.store');
        Route::put('suppliers/{vendor}', [$c.'SupplierController', 'update'])->name('suppliers.update');

        Route::get('requisitions', [$c.'RequisitionController', 'index'])->name('requisitions.index');
        Route::get('requisitions/create', [$c.'RequisitionController', 'create'])->name('requisitions.create');
        Route::post('requisitions', [$c.'RequisitionController', 'store'])->name('requisitions.store');
        Route::get('requisitions/{requisition}', [$c.'RequisitionController', 'show'])->name('requisitions.show');
        Route::post('requisitions/{requisition}/approve', [$c.'RequisitionController', 'approve'])->name('requisitions.approve');
        Route::post('requisitions/{requisition}/reject', [$c.'RequisitionController', 'reject'])->name('requisitions.reject');
        Route::post('requisitions/{requisition}/cancel', [$c.'RequisitionController', 'cancel'])->name('requisitions.cancel');

        Route::get('orders', [$c.'PurchaseOrderController', 'index'])->name('orders.index');
        Route::get('orders/create', [$c.'PurchaseOrderController', 'create'])->name('orders.create');
        Route::post('orders', [$c.'PurchaseOrderController', 'store'])->name('orders.store');
        Route::get('orders/{order}', [$c.'PurchaseOrderController', 'show'])->name('orders.show');
        Route::get('orders/{order}/print', [$c.'PurchaseOrderController', 'print'])->name('orders.print');
        Route::post('orders/{order}/approve', [$c.'PurchaseOrderController', 'approve'])->name('orders.approve');
        Route::post('orders/{order}/cancel', [$c.'PurchaseOrderController', 'cancel'])->name('orders.cancel');

        Route::get('receipts', [$c.'GoodsReceiptController', 'index'])->name('receipts.index');
        Route::get('receipts/create', [$c.'GoodsReceiptController', 'create'])->name('receipts.create');
        Route::post('receipts', [$c.'GoodsReceiptController', 'store'])->name('receipts.store');
        Route::get('receipts/{receipt}', [$c.'GoodsReceiptController', 'show'])->name('receipts.show');
        Route::post('receipts/{receipt}/reverse', [$c.'GoodsReceiptController', 'reverse'])->name('receipts.reverse');
        Route::post('receipts/{receipt}/pay', [$c.'GoodsReceiptController', 'pay'])->name('receipts.pay');
        Route::post('payments/{payment}/reverse', [$c.'GoodsReceiptController', 'reversePayment'])->name('payments.reverse');

        Route::get('issues', [$c.'IssueController', 'index'])->name('issues.index');
        Route::get('issues/create', [$c.'IssueController', 'create'])->name('issues.create');
        Route::post('issues', [$c.'IssueController', 'store'])->name('issues.store');
        Route::get('issues/{issue}', [$c.'IssueController', 'show'])->name('issues.show');
        Route::post('issues/{issue}/reverse', [$c.'IssueController', 'reverse'])->name('issues.reverse');

        Route::get('adjustments', [$c.'AdjustmentController', 'index'])->name('adjustments.index');
        Route::get('adjustments/create', [$c.'AdjustmentController', 'create'])->name('adjustments.create');
        Route::post('adjustments', [$c.'AdjustmentController', 'store'])->name('adjustments.store');
        Route::get('adjustments/{adjustment}', [$c.'AdjustmentController', 'show'])->name('adjustments.show');
        Route::post('adjustments/{adjustment}/reverse', [$c.'AdjustmentController', 'reverse'])->name('adjustments.reverse');

        Route::get('audits', [$c.'AuditController', 'index'])->name('audits.index');
        Route::post('audits', [$c.'AuditController', 'store'])->name('audits.store');
        Route::get('audits/{audit}', [$c.'AuditController', 'show'])->name('audits.show');
        Route::put('audits/{audit}/counts', [$c.'AuditController', 'counts'])->name('audits.counts');
        Route::post('audits/{audit}/complete', [$c.'AuditController', 'complete'])->name('audits.complete');
        Route::post('audits/{audit}/cancel', [$c.'AuditController', 'cancel'])->name('audits.cancel');

        Route::get('reports/stock', [$c.'ReportController', 'stock'])->name('reports.stock');
        Route::get('reports/consumption', [$c.'ReportController', 'consumption'])->name('reports.consumption');
        Route::get('reports/purchases', [$c.'ReportController', 'purchases'])->name('reports.purchases');
    });

    // Asset register (Part 6c-2): register, allocation, maintenance & calibration, depreciation, disposal, physical verification
    Route::prefix('assets')->name('assets.')->group(function () {
        $c = 'App\\Http\\Controllers\\Branch\\Assets\\';
        Route::get('/', [$c.'OverviewController', 'index'])->name('index');

        Route::get('categories', [$c.'CategoryController', 'index'])->name('categories.index');
        Route::post('categories', [$c.'CategoryController', 'store'])->name('categories.store');
        Route::put('categories/{category}', [$c.'CategoryController', 'update'])->name('categories.update');
        Route::delete('categories/{category}', [$c.'CategoryController', 'destroy'])->name('categories.destroy');

        Route::get('register', [$c.'AssetController', 'index'])->name('register.index');
        Route::get('register/create', [$c.'AssetController', 'create'])->name('register.create');
        Route::post('register', [$c.'AssetController', 'store'])->name('register.store');
        Route::get('register/from-stock', [$c.'AssetController', 'fromStock'])->name('register.from-stock');
        Route::post('register/from-stock', [$c.'AssetController', 'storeFromStock'])->name('register.from-stock.store');
        Route::get('register/labels', [$c.'AssetController', 'labels'])->name('register.labels');
        Route::get('register/export', [$c.'AssetController', 'export'])->name('register.export');
        Route::get('register/{asset}', [$c.'AssetController', 'show'])->name('register.show');
        Route::get('register/{asset}/edit', [$c.'AssetController', 'edit'])->name('register.edit');
        Route::put('register/{asset}', [$c.'AssetController', 'update'])->name('register.update');
        Route::post('register/{asset}/void', [$c.'AssetController', 'void'])->name('register.void');

        Route::get('allocations', [$c.'AllocationController', 'index'])->name('allocations.index');
        Route::post('register/{asset}/allocate', [$c.'AllocationController', 'store'])->name('allocations.store');
        Route::post('allocations/{allocation}/return', [$c.'AllocationController', 'return'])->name('allocations.return');

        Route::get('maintenance', [$c.'MaintenanceController', 'index'])->name('maintenance.index');
        Route::get('maintenance/plans', [$c.'MaintenanceController', 'plans'])->name('maintenance.plans');
        Route::post('register/{asset}/plans', [$c.'MaintenanceController', 'storePlan'])->name('maintenance.plans.store');
        Route::put('maintenance/plans/{plan}', [$c.'MaintenanceController', 'updatePlan'])->name('maintenance.plans.update');
        Route::post('maintenance/plans/{plan}/done', [$c.'MaintenanceController', 'donePlan'])->name('maintenance.plans.done');
        Route::post('register/{asset}/orders', [$c.'MaintenanceController', 'storeOrder'])->name('maintenance.orders.store');
        Route::get('maintenance/orders/{log}', [$c.'MaintenanceController', 'showOrder'])->name('maintenance.orders.show');
        Route::post('maintenance/orders/{log}/start', [$c.'MaintenanceController', 'start'])->name('maintenance.orders.start');
        Route::post('maintenance/orders/{log}/complete', [$c.'MaintenanceController', 'complete'])->name('maintenance.orders.complete');
        Route::post('maintenance/orders/{log}/cancel', [$c.'MaintenanceController', 'cancel'])->name('maintenance.orders.cancel');

        Route::get('depreciation', [$c.'DepreciationController', 'index'])->name('depreciation.index');
        Route::post('depreciation', [$c.'DepreciationController', 'run'])->name('depreciation.run');
        Route::get('depreciation/{run}', [$c.'DepreciationController', 'show'])->name('depreciation.show');
        Route::post('depreciation/{run}/reverse', [$c.'DepreciationController', 'reverse'])->name('depreciation.reverse');

        Route::get('disposals', [$c.'DisposalController', 'index'])->name('disposals.index');
        Route::get('register/{asset}/dispose', [$c.'DisposalController', 'create'])->name('disposals.create');
        Route::post('register/{asset}/dispose', [$c.'DisposalController', 'store'])->name('disposals.store');
        Route::get('disposals/{disposal}', [$c.'DisposalController', 'show'])->name('disposals.show');
        Route::post('disposals/{disposal}/reverse', [$c.'DisposalController', 'reverse'])->name('disposals.reverse');

        Route::get('audits', [$c.'AuditController', 'index'])->name('audits.index');
        Route::post('audits', [$c.'AuditController', 'store'])->name('audits.store');
        Route::get('audits/{audit}', [$c.'AuditController', 'show'])->name('audits.show');
        Route::put('audits/{audit}/results', [$c.'AuditController', 'results'])->name('audits.results');
        Route::post('audits/{audit}/complete', [$c.'AuditController', 'complete'])->name('audits.complete');
        Route::post('audits/{audit}/cancel', [$c.'AuditController', 'cancel'])->name('audits.cancel');

        Route::get('reports/summary', [$c.'ReportController', 'summary'])->name('reports.summary');
        Route::get('reports/depreciation', [$c.'ReportController', 'depreciation'])->name('reports.depreciation');
        Route::get('reports/maintenance', [$c.'ReportController', 'maintenance'])->name('reports.maintenance');
    });

    // Learning — homework & assignments, class materials, progress (Part 5-3)
    Route::prefix('learning')->name('learning.')->group(base_path('routes/learning.php'));
    // Zoom / Google Meet connection for this branch (back office only)
    Route::prefix('learning/integrations')->name('learning.integrations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Branch\Learning\OnlineClassIntegrationController::class, 'index'])->name('index');
        Route::put('{provider}', [\App\Http\Controllers\Branch\Learning\OnlineClassIntegrationController::class, 'update'])->where('provider', 'zoom|google_meet')->name('update');
        Route::post('{provider}/test', [\App\Http\Controllers\Branch\Learning\OnlineClassIntegrationController::class, 'test'])->where('provider', 'zoom|google_meet')->middleware('throttle:10,1')->name('test');
        Route::delete('{provider}', [\App\Http\Controllers\Branch\Learning\OnlineClassIntegrationController::class, 'disconnect'])->where('provider', 'zoom|google_meet')->name('disconnect');
        Route::get('google/connect', [\App\Http\Controllers\Branch\Learning\OnlineClassIntegrationController::class, 'googleRedirect'])->name('google.connect');
        Route::get('google/callback', [\App\Http\Controllers\Branch\Learning\OnlineClassIntegrationController::class, 'googleCallback'])->name('google.callback');
    });

    // Communication — notice board, bulk messages, templates, providers (Part 5-1)
    Route::prefix('communication')->name('communication.')->group(function () {
        Route::get('notices', [NoticeController::class, 'index'])->name('notices.index');
        Route::get('notices/create', [NoticeController::class, 'create'])->name('notices.create');
        Route::post('notices', [NoticeController::class, 'store'])->name('notices.store');
        Route::get('notices/{notice}', [NoticeController::class, 'show'])->name('notices.show');
        Route::get('notices/{notice}/edit', [NoticeController::class, 'edit'])->name('notices.edit');
        Route::put('notices/{notice}', [NoticeController::class, 'update'])->name('notices.update');
        Route::delete('notices/{notice}', [NoticeController::class, 'destroy'])->name('notices.destroy');
        Route::post('notices/{notice}/publish', [NoticeController::class, 'publish'])->name('notices.publish');
        Route::post('notices/{notice}/unschedule', [NoticeController::class, 'unschedule'])->name('notices.unschedule');
        Route::post('notices/{notice}/archive', [NoticeController::class, 'archive'])->name('notices.archive');
        Route::get('notices/{notice}/attachment', [NoticeController::class, 'attachment'])->name('notices.attachment');

        Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
        Route::get('messages/create', [MessageController::class, 'create'])->name('messages.create');
        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
        Route::get('messages/usage', [MessageController::class, 'usage'])->name('messages.usage');
        Route::get('students', [MessageController::class, 'students'])->middleware('throttle:120,1')->name('students.search');
        Route::post('messages/retry-failed', [MessageController::class, 'retryFailed'])->name('messages.retry-failed');
        Route::post('messages/batches/{batch}/cancel', [MessageController::class, 'cancelBatch'])->name('messages.batches.cancel');
        Route::get('messages/{message}', [MessageController::class, 'show'])->name('messages.show');
        Route::post('messages/{message}/retry', [MessageController::class, 'retry'])->name('messages.retry');
        Route::post('messages/{message}/cancel', [MessageController::class, 'cancel'])->name('messages.cancel');

        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('templates/{event}', [TemplateController::class, 'edit'])->where('event', '[a-z_.]+')->name('templates.edit');
        Route::put('templates/{event}', [TemplateController::class, 'update'])->where('event', '[a-z_.]+')->name('templates.update');
        Route::delete('templates/{event}', [TemplateController::class, 'reset'])->where('event', '[a-z_.]+')->name('templates.reset');

        Route::get('settings', [NotificationSettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [NotificationSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/test', [NotificationSettingsController::class, 'test'])->middleware('throttle:10,1')->name('settings.test');

        Route::get('opt-outs', [OptOutController::class, 'index'])->name('opt-outs.index');
        Route::post('opt-outs', [OptOutController::class, 'store'])->name('opt-outs.store');
        Route::delete('opt-outs/{optOut}', [OptOutController::class, 'destroy'])->name('opt-outs.destroy');
    });

    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('transfers/{transfer}/accept', [TransferController::class, 'accept'])->name('transfers.accept');
    Route::post('transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');

    Route::get('certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('certificates/{certificate}/pdf', [CertificateController::class, 'pdf'])->name('certificates.pdf');
    Route::post('certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])->name('certificates.revoke');

    // ---------------------------------------------------------------- staff & HR (Part 3B)
    Route::prefix('hr')->name('hr.')->group(function () {
        Route::resource('designations', DesignationController::class)->except(['show']);
        Route::resource('leave-types', LeaveTypeController::class)->except(['show', 'destroy']);

        Route::get('certificates', [\App\Http\Controllers\Branch\HR\StaffCertificateController::class, 'index'])->name('certificates.index');
        Route::post('employees/{employee}/certificates', [\App\Http\Controllers\Branch\HR\StaffCertificateController::class, 'store'])->name('employees.certificates.store');
        Route::get('certificates/{certificate}/pdf', [\App\Http\Controllers\Branch\HR\StaffCertificateController::class, 'pdf'])->name('certificates.pdf');
        Route::post('certificates/{certificate}/revoke', [\App\Http\Controllers\Branch\HR\StaffCertificateController::class, 'revoke'])->name('certificates.revoke');

        Route::resource('employees', EmployeeController::class)->except(['destroy']);
        Route::patch('employees/{employee}/status', [EmployeeController::class, 'changeStatus'])->name('employees.status');
        Route::post('employees/{employee}/account', [EmployeeController::class, 'createAccount'])->name('employees.account');
        Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
        Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('employees.documents.store');
        Route::get('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'download'])->name('employees.documents.download');
        Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('employees.documents.destroy');
    });

    // Leave applications (HR desk + staff members' own applications)
    Route::get('leaves', [LeaveApplicationController::class, 'index'])->name('leaves.index');
    Route::get('leaves/create', [LeaveApplicationController::class, 'create'])->name('leaves.create');
    Route::post('leaves', [LeaveApplicationController::class, 'store'])->name('leaves.store');
    Route::post('leaves/{leave}/approve', [LeaveApplicationController::class, 'approve'])->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [LeaveApplicationController::class, 'reject'])->name('leaves.reject');
    Route::post('leaves/{leave}/cancel', [LeaveApplicationController::class, 'cancel'])->name('leaves.cancel');

    // ---------------------------------------------------------------- attendance (Part 3B)
    Route::prefix('attendance')->name('attendance.')->group(function () {
        Route::get('students', [StudentAttendanceController::class, 'index'])->name('students.index');
        Route::get('students/sections/{section}', [StudentAttendanceController::class, 'register'])->name('students.register');
        Route::post('students', [StudentAttendanceController::class, 'store'])->name('students.store');

        Route::get('staff', [StaffAttendanceController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffAttendanceController::class, 'store'])->name('staff.store');

        Route::get('scan', [AttendanceScanController::class, 'index'])->name('scan');
        Route::post('scan', [AttendanceScanController::class, 'store'])->middleware('throttle:120,1')->name('scan.store');

        Route::get('corrections', [AttendanceCorrectionController::class, 'index'])->name('corrections.index');
        Route::post('corrections', [AttendanceCorrectionController::class, 'store'])->name('corrections.store');
        Route::post('corrections/{correction}/approve', [AttendanceCorrectionController::class, 'approve'])->name('corrections.approve');
        Route::post('corrections/{correction}/reject', [AttendanceCorrectionController::class, 'reject'])->name('corrections.reject');

        Route::get('reports/students', [AttendanceReportController::class, 'students'])->name('reports.students');
        Route::get('reports/staff', [AttendanceReportController::class, 'staff'])->name('reports.staff');
        Route::get('alerts', [AttendanceReportController::class, 'alerts'])->name('alerts');
        Route::post('alerts/{alert}/acknowledge', [AttendanceReportController::class, 'acknowledge'])->name('alerts.acknowledge');

        Route::get('settings', [AttendanceSettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [AttendanceSettingsController::class, 'update'])->name('settings.update');
        Route::post('devices', [AttendanceSettingsController::class, 'storeDevice'])->name('devices.store');
        Route::post('devices/{device}/rotate', [AttendanceSettingsController::class, 'rotateDevice'])->name('devices.rotate');
        Route::post('devices/{device}/toggle', [AttendanceSettingsController::class, 'toggleDevice'])->name('devices.toggle');
    });
});

// Everyone signed in to the branch (all portals): in-app inbox and notice board — Part 5-1
Route::middleware(['auth', 'tenant.access'])->group(function () {
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/unread', [InboxController::class, 'unread'])->name('inbox.unread');
    Route::get('/inbox/{message}', [InboxController::class, 'open'])->name('inbox.open');
    Route::post('/inbox/read-all', [InboxController::class, 'readAll'])->name('inbox.read-all');
    Route::get('/notices', [NoticeBoardController::class, 'index'])->name('board.index');
    Route::get('/notices/{notice}', [NoticeBoardController::class, 'show'])->name('board.show');
    Route::get('/notices/{notice}/attachment', [NoticeBoardController::class, 'attachment'])->name('board.attachment');
    Route::get('/message-preferences', [MessagePreferencesController::class, 'edit'])->middleware('portal:student,parent')->name('preferences.edit');
    Route::put('/message-preferences', [MessagePreferencesController::class, 'update'])->middleware('portal:student,parent')->name('preferences.update');
});

// Online fee payments (Part 5-2): payers start and follow their payment …
Route::middleware(['auth', 'tenant.access', 'portal:student,parent'])->group(function () {
    Route::post('/online-payments/start/{student}', [OnlinePaymentController::class, 'start'])->middleware('throttle:10,1')->name('payments.start');
    Route::get('/online-payments/status/{tran}', [OnlinePaymentController::class, 'status'])->where('tran', '[A-Z0-9]+')->name('payments.status');
    Route::get('/online-payments/qr/{tran}', [OnlinePaymentController::class, 'qr'])->where('tran', '[A-Z0-9]+')->name('payments.qr');
    Route::post('/online-payments/qr/{tran}/claim', [OnlinePaymentController::class, 'claim'])->where('tran', '[A-Z0-9]+')->middleware('throttle:10,1')->name('payments.qr.claim');
});
// … and gateways send them back / notify us. No session here: a cross-site POST has no session cookie and must not replace it.
// tran + result live in the path: gateways append their own query string to the callback URL.
Route::match(['get', 'post'], '/online-payments/{gateway}/return/{tran}/{result}', [GatewayCallbackController::class, 'return'])
    ->where(['tran' => '[A-Z0-9]+', 'result' => 'success|fail|cancel'])
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->middleware('throttle:120,1')->name('payments.return');
// ShatajPay: one fixed callback URL per merchant app (path as in the ShatajPay integration guide).
Route::get('/gateway/payment/callback', [GatewayCallbackController::class, 'shatajpay'])
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->middleware('throttle:120,1')->name('payments.shatajpay.callback');
Route::get('/online-payments/done/{tran}', [GatewayCallbackController::class, 'done'])->where('tran', '[A-Z0-9]+')->middleware('throttle:60,1')->name('payments.done');
Route::post('/online-payments/{gateway}/ipn', [GatewayCallbackController::class, 'ipn'])
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
    ->middleware('throttle:300,1')->name('payments.ipn');

// WhatsApp Cloud API webhook of this branch (no session, no CSRF; the signature is checked).
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:60,1')->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:600,1')->name('webhooks.whatsapp');

Route::middleware(['auth', 'tenant.access'])->group(function () {
    Route::get('/switch-branch', [SwitchBranchController::class, 'index'])->name('branch.switch-branch');
    Route::post('/switch-branch/{branch}', [SwitchBranchController::class, 'switch'])->name('branch.switch-branch.go');
    Route::post('/switch-branch-central', [SwitchBranchController::class, 'central'])->name('branch.switch-central');
});
