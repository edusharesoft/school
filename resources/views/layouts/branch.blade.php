@extends('layouts.shell')

@php
    $u = auth()->user();
    $is = fn (...$r) => request()->routeIs(...$r);
    $b = $currentBranch;
    $brand = [
        'home' => route('home.redirect'),
        'name' => $b?->displayName(),
        'sub' => $b?->host(),
        'logo' => \App\Support\AuthTheme::logo($b),
        'icon' => 'fa-solid fa-school',
        'gradient' => 'linear-gradient(135deg,#6366f1,#ec4899)',
        'badge' => $b ? $b->code.' · '.($b->institution?->code ?? '') : null,
    ];
    $notificationsUrl = $u->can('audit-logs.view') ? route('branch.audit-logs.index') : null;
    $canStudents = $u->can('viewAny', App\Models\Students\Student::class);
    $canAcademics = $u->can('viewAny', App\Models\Academic\SchoolClass::class);
    // Menu badges (cheap indexed counts; branch-scoped by the global scope)
    $onlineReview = $u->can('payments.reconcile') ? App\Models\Payments\OnlinePayment::query()->where('status', 'review')->count() : 0;
    $pendingMarkCorrections = $u->can('marks.approve-correction') ? App\Models\Exams\MarkCorrection::query()->where('status', 'pending')->count() : 0;
    $pendingLeaves = $u->can('leaves.approve') ? App\Models\HR\LeaveApplication::query()->where('status', 'pending')->count() : 0;
    $pendingCorrections = ($u->can('student-attendance.approve-correction') || $u->can('staff-attendance.approve-correction'))
        ? App\Models\Attendance\AttendanceCorrection::query()->where('status', 'pending')->count() : 0;
    $newApplications = $u->can('admissions.view') ? App\Models\Admissions\AdmissionApplication::query()->where('status', 'submitted')->count() : 0;
    $incomingTransfers = $u->can('students.transfer') ? App\Models\Students\StudentTransfer::query()->where('to_branch_id', $b?->id)->where('status', 'pending')->count() : 0;
    $pendingRequisitions = $u->can('inventory.approve') ? App\Models\Inventory\Requisition::query()->where('status', 'pending')->count() : 0;
    $overdueMaintenance = $u->can('assets.view') ? App\Models\Assets\MaintenancePlan::query()->open()->whereDate('next_due_on', '<', now()->toDateString())->count() : 0;
    $openAlerts = $u->can('student-attendance.view') ? App\Models\Attendance\AttendanceAlert::query()->where('status', 'open')->count() : 0;
    $menu = [
        ['label' => __('Dashboard'), 'icon' => 'fa-solid fa-gauge-high', 'color' => '#6366f1', 'url' => route('branch.dashboard'), 'active' => $is('branch.dashboard*'), 'show' => $u->can('dashboard.branch')],
        ['label' => __('Students'), 'icon' => 'fa-solid fa-user-graduate', 'color' => '#0ea5e9', 'show' => $canStudents, 'children' => [
            ['label' => __('Student list'), 'icon' => 'fa-solid fa-list-ul', 'url' => route('branch.students.index'), 'active' => $is('branch.students.index', 'branch.students.show', 'branch.students.edit')],
            ['label' => __('New admission'), 'icon' => 'fa-solid fa-user-plus', 'color' => '#16a34a', 'url' => route('branch.students.create'), 'active' => $is('branch.students.create'), 'show' => $u->can('create', App\Models\Students\Student::class)],
            ['label' => __('Promotion'), 'icon' => 'fa-solid fa-arrow-up-wide-short', 'color' => '#f59e0b', 'url' => route('branch.students.promotion'), 'active' => $is('branch.students.promotion*'), 'show' => $u->can('promote', App\Models\Students\Student::class)],
            ['label' => __('Import'), 'icon' => 'fa-solid fa-file-import', 'color' => '#8b5cf6', 'url' => route('branch.students.import'), 'active' => $is('branch.students.import*'), 'show' => $u->can('import', App\Models\Students\Student::class)],
            ['label' => __('Branch transfers'), 'icon' => 'fa-solid fa-right-left', 'color' => '#0ea5e9', 'url' => route('branch.transfers.index'), 'active' => $is('branch.transfers.*'), 'badge' => $incomingTransfers, 'show' => $u->can('students.transfer')],
            ['label' => __('Certificates'), 'icon' => 'fa-solid fa-award', 'color' => '#f97316', 'url' => route('branch.certificates.index'), 'active' => $is('branch.certificates.*'), 'show' => $u->can('certificates.view')],
            ['label' => __('ID cards'), 'icon' => 'fa-solid fa-id-badge', 'color' => '#0ea5e9', 'url' => route('branch.idcards.index'), 'active' => $is('branch.idcards.*'), 'show' => $u->can('id-cards.view')],
            ['label' => __('Document settings'), 'icon' => 'fa-solid fa-signature', 'color' => '#64748b', 'url' => route('branch.documents.settings'), 'active' => $is('branch.documents.*'), 'show' => $u->can('settings.view')],
        ]],
        ['label' => __('Academics'), 'icon' => 'fa-solid fa-book-open-reader', 'color' => '#f97316', 'show' => $canAcademics || $u->can('timetables.view'), 'children' => [
            ['label' => __('Sessions & terms'), 'icon' => 'fa-solid fa-calendar-days', 'url' => route('branch.academics.sessions.index'), 'active' => $is('branch.academics.sessions.*'), 'show' => $canAcademics],
            ['label' => __('Classes & sections'), 'icon' => 'fa-solid fa-chalkboard', 'color' => '#16a34a', 'url' => route('branch.academics.classes.index'), 'active' => $is('branch.academics.classes.*', 'branch.academics.sections.*'), 'show' => $canAcademics],
            ['label' => __('Subjects'), 'icon' => 'fa-solid fa-book', 'color' => '#0ea5e9', 'url' => route('branch.academics.subjects.index'), 'active' => $is('branch.academics.subjects.*'), 'show' => $canAcademics],
            ['label' => __('Groups'), 'icon' => 'fa-solid fa-layer-group', 'color' => '#8b5cf6', 'url' => route('branch.academics.groups.index'), 'active' => $is('branch.academics.groups.*'), 'show' => $canAcademics],
            ['label' => __('Rooms'), 'icon' => 'fa-solid fa-door-open', 'color' => '#14b8a6', 'url' => route('branch.academics.rooms.index'), 'active' => $is('branch.academics.rooms.*'), 'show' => $canAcademics],
            ['label' => __('Class routine'), 'icon' => 'fa-solid fa-calendar-week', 'color' => '#8b5cf6', 'url' => route('branch.academics.timetable.index'), 'active' => $is('branch.academics.timetable.*'), 'show' => $u->can('timetables.view')],
            ['label' => __('Periods'), 'icon' => 'fa-solid fa-clock', 'color' => '#64748b', 'url' => route('branch.academics.periods.index'), 'active' => $is('branch.academics.periods.*'), 'show' => $u->can('timetables.view')],
        ]],
        ['label' => __('Attendance'), 'icon' => 'fa-solid fa-clipboard-check', 'color' => '#16a34a', 'children' => [
            ['label' => __('Student registers'), 'icon' => 'fa-solid fa-user-check', 'url' => route('branch.attendance.students.index'), 'active' => $is('branch.attendance.students.*'), 'show' => $u->can('student-attendance.view') || $u->can('student-attendance.mark')],
            ['label' => __('Teachers & staff'), 'icon' => 'fa-solid fa-user-tie', 'color' => '#6366f1', 'url' => route('branch.attendance.staff.index'), 'active' => $is('branch.attendance.staff.*'), 'show' => $u->can('staff-attendance.view')],
            ['label' => __('Scan station'), 'icon' => 'fa-solid fa-qrcode', 'color' => '#0ea5e9', 'url' => route('branch.attendance.scan'), 'active' => $is('branch.attendance.scan*'), 'show' => $u->can('student-attendance.mark') || $u->can('staff-attendance.mark')],
            ['label' => __('Corrections'), 'icon' => 'fa-solid fa-pen-to-square', 'color' => '#f59e0b', 'url' => route('branch.attendance.corrections.index'), 'active' => $is('branch.attendance.corrections.*'), 'badge' => $pendingCorrections, 'show' => $u->can('student-attendance.correct') || $u->can('staff-attendance.correct') || $u->can('student-attendance.approve-correction') || $u->can('staff-attendance.approve-correction')],
            ['label' => __('Monthly sheets'), 'icon' => 'fa-solid fa-table-cells', 'color' => '#8b5cf6', 'url' => route('branch.attendance.reports.students'), 'active' => $is('branch.attendance.reports.*'), 'show' => $u->can('student-attendance.view') || $u->can('staff-attendance.view')],
            ['label' => __('Absence alerts'), 'icon' => 'fa-solid fa-triangle-exclamation', 'color' => '#ef4444', 'url' => route('branch.attendance.alerts'), 'active' => $is('branch.attendance.alerts*'), 'badge' => $openAlerts, 'show' => $u->can('student-attendance.view')],
            ['label' => __('Rules & devices'), 'icon' => 'fa-solid fa-sliders', 'color' => '#64748b', 'url' => route('branch.attendance.settings'), 'active' => $is('branch.attendance.settings*'), 'show' => $u->can('settings.view')],
        ]],
        ['label' => __('Admissions'), 'icon' => 'fa-solid fa-id-card', 'color' => '#ec4899', 'show' => $u->can('admissions.view'), 'children' => [
            ['label' => __('Applications'), 'icon' => 'fa-solid fa-inbox', 'url' => route('branch.admissions.applications.index'), 'active' => $is('branch.admissions.applications.*'), 'badge' => $newApplications],
            ['label' => __('Admission rounds'), 'icon' => 'fa-solid fa-calendar-plus', 'color' => '#6366f1', 'url' => route('branch.admissions.campaigns.index'), 'active' => $is('branch.admissions.campaigns.*', 'branch.admissions.merit.*')],
            ['label' => __('Public admission page'), 'icon' => 'fa-solid fa-globe', 'color' => '#16a34a', 'url' => route('admission.index'), 'blank' => true],
        ]],
        ['label' => __('Staff & HR'), 'icon' => 'fa-solid fa-id-card-clip', 'color' => '#a855f7', 'children' => [
            ['label' => __('Employees'), 'icon' => 'fa-solid fa-users', 'url' => route('branch.hr.employees.index'), 'active' => $is('branch.hr.employees.*'), 'show' => $u->can('viewAny', App\Models\HR\Employee::class)],
            ['label' => __('Staff certificates'), 'icon' => 'fa-solid fa-award', 'color' => '#ec4899', 'url' => route('branch.hr.certificates.index'), 'active' => $is('branch.hr.certificates.*'), 'show' => $u->can('staff-certificates.view')],
            ['label' => __('Leave applications'), 'icon' => 'fa-solid fa-plane-departure', 'color' => '#0ea5e9', 'url' => route('branch.leaves.index'), 'active' => $is('branch.leaves.*'), 'badge' => $pendingLeaves, 'show' => $u->can('viewAny', App\Models\HR\LeaveApplication::class)],
            ['label' => __('Leave types'), 'icon' => 'fa-solid fa-list-check', 'color' => '#14b8a6', 'url' => route('branch.hr.leave-types.index'), 'active' => $is('branch.hr.leave-types.*'), 'show' => $u->can('leaves.update')],
            ['label' => __('Designations'), 'icon' => 'fa-solid fa-medal', 'color' => '#f59e0b', 'url' => route('branch.hr.designations.index'), 'active' => $is('branch.hr.designations.*'), 'show' => $u->can('employees.view')],
            ['label' => __('Departments'), 'icon' => 'fa-solid fa-sitemap', 'color' => '#6366f1', 'url' => route('branch.departments.index'), 'active' => $is('branch.departments.*'), 'show' => $u->can('viewAny', App\Models\Department::class)],
        ]],
        ['label' => __('Learning'), 'icon' => 'fa-solid fa-book-open-reader', 'color' => '#f97316', 'show' => $u->can('homework.view') || $u->can('assignments.view') || $u->can('materials.view') || $u->can('online-classes.view') || $u->can('quizzes.view'), 'children' => [
            ['label' => __('Homework & assignments'), 'icon' => 'fa-solid fa-book', 'url' => route('branch.learning.assignments.index'), 'active' => $is('branch.learning.assignments.*'), 'show' => $u->can('homework.view') || $u->can('assignments.view')],
            ['label' => __('Class materials'), 'icon' => 'fa-solid fa-folder-open', 'color' => '#0ea5e9', 'url' => route('branch.learning.materials.index'), 'active' => $is('branch.learning.materials.*'), 'show' => $u->can('materials.view')],
            ['label' => __('Online & offline classes'), 'icon' => 'fa-solid fa-video', 'color' => '#6366f1', 'url' => route('branch.learning.online-classes.index'), 'active' => $is('branch.learning.online-classes.*'), 'show' => $u->can('online-classes.view')],
            ['label' => __('Quizzes'), 'icon' => 'fa-solid fa-circle-question', 'color' => '#ec4899', 'url' => route('branch.learning.quizzes.index'), 'active' => $is('branch.learning.quizzes.*'), 'show' => $u->can('quizzes.view')],
            ['label' => __('Learning progress'), 'icon' => 'fa-solid fa-chart-line', 'color' => '#16a34a', 'url' => route('branch.learning.progress.index'), 'active' => $is('branch.learning.progress.*'), 'show' => $u->can('homework.view') || $u->can('assignments.view')],
            ['label' => __('Zoom & Google Meet'), 'icon' => 'fa-solid fa-plug', 'color' => '#64748b', 'url' => route('branch.learning.integrations.index'), 'active' => $is('branch.learning.integrations.*'), 'show' => $u->can('integrations.view')],
        ]],
        ['label' => __('Library'), 'icon' => 'fa-solid fa-book-bookmark', 'color' => '#0d9488', 'show' => $u->can('library.view'), 'children' => [
            ['label' => __('Overview'), 'icon' => 'fa-solid fa-gauge', 'url' => route('branch.library.index'), 'active' => $is('branch.library.index')],
            ['label' => __('Circulation desk'), 'icon' => 'fa-solid fa-barcode', 'color' => '#2563eb', 'url' => route('branch.library.desk'), 'active' => $is('branch.library.desk', 'branch.library.member'), 'show' => $u->can('library.issue')],
            ['label' => __('Books'), 'icon' => 'fa-solid fa-book', 'color' => '#f97316', 'url' => route('branch.library.books.index'), 'active' => $is('branch.library.books.*')],
            ['label' => __('Loans'), 'icon' => 'fa-solid fa-hand-holding', 'color' => '#8b5cf6', 'url' => route('branch.library.loans'), 'active' => $is('branch.library.loans')],
            ['label' => __('Reservations'), 'icon' => 'fa-solid fa-bookmark', 'color' => '#0ea5e9', 'url' => route('branch.library.reservations.index'), 'active' => $is('branch.library.reservations.*')],
            ['label' => __('Fines'), 'icon' => 'fa-solid fa-coins', 'color' => '#ef4444', 'url' => route('branch.library.fines.index'), 'active' => $is('branch.library.fines.*'), 'show' => $u->can('library.issue')],
            ['label' => __('Categories'), 'icon' => 'fa-solid fa-tags', 'color' => '#14b8a6', 'url' => route('branch.library.categories.index'), 'active' => $is('branch.library.categories.*')],
            ['label' => __('Library rules'), 'icon' => 'fa-solid fa-sliders', 'color' => '#64748b', 'url' => route('branch.library.rules.edit'), 'active' => $is('branch.library.rules.*'), 'show' => $u->can('library.update')],
        ]],
        ['label' => __('Transport'), 'icon' => 'fa-solid fa-bus', 'color' => '#f59e0b', 'show' => $u->can('transport.view'), 'children' => [
            ['label' => __('Overview'), 'icon' => 'fa-solid fa-gauge', 'url' => route('branch.transport.index'), 'active' => $is('branch.transport.index')],
            ['label' => __('Routes & stops'), 'icon' => 'fa-solid fa-route', 'color' => '#2563eb', 'url' => route('branch.transport.routes.index'), 'active' => $is('branch.transport.routes.*', 'branch.transport.stops.*')],
            ['label' => __('Student seats'), 'icon' => 'fa-solid fa-user-check', 'color' => '#16a34a', 'url' => route('branch.transport.allocations.index'), 'active' => $is('branch.transport.allocations.*')],
            ['label' => __('Vehicles'), 'icon' => 'fa-solid fa-van-shuttle', 'color' => '#f97316', 'url' => route('branch.transport.vehicles.index'), 'active' => $is('branch.transport.vehicles.*', 'branch.transport.maintenance.*', 'branch.transport.fuel.*')],
            ['label' => __('Drivers & helpers'), 'icon' => 'fa-solid fa-id-card', 'color' => '#8b5cf6', 'url' => route('branch.transport.drivers.index'), 'active' => $is('branch.transport.drivers.*')],
            ['label' => __('Driver attendance'), 'icon' => 'fa-solid fa-calendar-check', 'color' => '#0ea5e9', 'url' => route('branch.transport.attendance'), 'active' => $is('branch.transport.attendance*')],
            ['label' => __('Transport fees'), 'icon' => 'fa-solid fa-coins', 'color' => '#ef4444', 'url' => route('branch.transport.fees'), 'active' => $is('branch.transport.fees*')],
        ]],
        ['label' => __('Hostel'), 'icon' => 'fa-solid fa-bed', 'color' => '#6366f1', 'show' => $u->can('hostel.view'), 'children' => [
            ['label' => __('Overview'), 'icon' => 'fa-solid fa-gauge', 'url' => route('branch.hostel.index'), 'active' => $is('branch.hostel.index', 'branch.hostel.buildings.*')],
            ['label' => __('Rooms & beds'), 'icon' => 'fa-solid fa-door-open', 'color' => '#2563eb', 'url' => route('branch.hostel.rooms.index'), 'active' => $is('branch.hostel.rooms.*', 'branch.hostel.beds.*')],
            ['label' => __('Residents'), 'icon' => 'fa-solid fa-user-check', 'color' => '#16a34a', 'url' => route('branch.hostel.allocations.index'), 'active' => $is('branch.hostel.allocations.*')],
            ['label' => __('Meal plans'), 'icon' => 'fa-solid fa-utensils', 'color' => '#f97316', 'url' => route('branch.hostel.meal-plans.index'), 'active' => $is('branch.hostel.meal-plans.*')],
            ['label' => __('Roll call'), 'icon' => 'fa-solid fa-calendar-check', 'color' => '#0ea5e9', 'url' => route('branch.hostel.attendance'), 'active' => $is('branch.hostel.attendance*')],
            ['label' => __('Visitors'), 'icon' => 'fa-solid fa-people-arrows', 'color' => '#8b5cf6', 'url' => route('branch.hostel.visitors.index'), 'active' => $is('branch.hostel.visitors.*')],
            ['label' => __('Maintenance'), 'icon' => 'fa-solid fa-screwdriver-wrench', 'color' => '#64748b', 'url' => route('branch.hostel.maintenance.index'), 'active' => $is('branch.hostel.maintenance.*')],
            ['label' => __('Hostel fees'), 'icon' => 'fa-solid fa-coins', 'color' => '#ef4444', 'url' => route('branch.hostel.fees'), 'active' => $is('branch.hostel.fees*')],
        ]],
        ['label' => __('Inventory'), 'icon' => 'fa-solid fa-boxes-stacked', 'color' => '#b45309', 'show' => $u->can('inventory.view'), 'children' => [
            ['label' => __('Overview'), 'icon' => 'fa-solid fa-gauge', 'url' => route('branch.inventory.index'), 'active' => $is('branch.inventory.index')],
            ['label' => __('Items & stock'), 'icon' => 'fa-solid fa-box-open', 'color' => '#2563eb', 'url' => route('branch.inventory.items.index'), 'active' => $is('branch.inventory.items.*', 'branch.inventory.categories.*')],
            ['label' => __('Purchase requests'), 'icon' => 'fa-solid fa-clipboard-list', 'color' => '#8b5cf6', 'url' => route('branch.inventory.requisitions.index'), 'active' => $is('branch.inventory.requisitions.*'), 'badge' => $pendingRequisitions],
            ['label' => __('Purchase orders'), 'icon' => 'fa-solid fa-file-invoice', 'color' => '#0ea5e9', 'url' => route('branch.inventory.orders.index'), 'active' => $is('branch.inventory.orders.*')],
            ['label' => __('Goods receiving'), 'icon' => 'fa-solid fa-truck-ramp-box', 'color' => '#16a34a', 'url' => route('branch.inventory.receipts.index'), 'active' => $is('branch.inventory.receipts.*', 'branch.inventory.payments.*')],
            ['label' => __('Suppliers'), 'icon' => 'fa-solid fa-shop', 'color' => '#f97316', 'url' => route('branch.inventory.suppliers.index'), 'active' => $is('branch.inventory.suppliers.*')],
            ['label' => __('Stock issues'), 'icon' => 'fa-solid fa-dolly', 'color' => '#ef4444', 'url' => route('branch.inventory.issues.index'), 'active' => $is('branch.inventory.issues.*')],
            ['label' => __('Stock adjustments'), 'icon' => 'fa-solid fa-scale-balanced', 'color' => '#64748b', 'url' => route('branch.inventory.adjustments.index'), 'active' => $is('branch.inventory.adjustments.*')],
            ['label' => __('Stock audits'), 'icon' => 'fa-solid fa-clipboard-check', 'color' => '#14b8a6', 'url' => route('branch.inventory.audits.index'), 'active' => $is('branch.inventory.audits.*')],
            ['label' => __('Reports'), 'icon' => 'fa-solid fa-chart-column', 'color' => '#f59e0b', 'url' => route('branch.inventory.reports.stock'), 'active' => $is('branch.inventory.reports.*')],
        ]],
        ['label' => __('Assets'), 'icon' => 'fa-solid fa-computer', 'color' => '#0e7490', 'show' => $u->can('assets.view'), 'children' => [
            ['label' => __('Overview'), 'icon' => 'fa-solid fa-gauge', 'url' => route('branch.assets.index'), 'active' => $is('branch.assets.index')],
            ['label' => __('Asset register'), 'icon' => 'fa-solid fa-barcode', 'color' => '#2563eb', 'url' => route('branch.assets.register.index'), 'active' => $is('branch.assets.register.*', 'branch.assets.categories.*')],
            ['label' => __('Register an asset'), 'icon' => 'fa-solid fa-plus', 'color' => '#16a34a', 'url' => route('branch.assets.register.create'), 'active' => $is('branch.assets.register.create'), 'show' => $u->can('assets.create')],
            ['label' => __('Allocations'), 'icon' => 'fa-solid fa-hand-holding', 'color' => '#8b5cf6', 'url' => route('branch.assets.allocations.index'), 'active' => $is('branch.assets.allocations.*')],
            ['label' => __('Work orders'), 'icon' => 'fa-solid fa-screwdriver-wrench', 'color' => '#f97316', 'url' => route('branch.assets.maintenance.index'), 'active' => $is('branch.assets.maintenance.index', 'branch.assets.maintenance.orders.*')],
            ['label' => __('Maintenance plans'), 'icon' => 'fa-solid fa-calendar-check', 'color' => '#ef4444', 'url' => route('branch.assets.maintenance.plans'), 'active' => $is('branch.assets.maintenance.plans'), 'badge' => $overdueMaintenance],
            ['label' => __('Depreciation'), 'icon' => 'fa-solid fa-arrow-trend-down', 'color' => '#0f766e', 'url' => route('branch.assets.depreciation.index'), 'active' => $is('branch.assets.depreciation.*')],
            ['label' => __('Disposals'), 'icon' => 'fa-solid fa-trash-can-arrow-up', 'color' => '#64748b', 'url' => route('branch.assets.disposals.index'), 'active' => $is('branch.assets.disposals.*')],
            ['label' => __('Verification'), 'icon' => 'fa-solid fa-clipboard-check', 'color' => '#14b8a6', 'url' => route('branch.assets.audits.index'), 'active' => $is('branch.assets.audits.*')],
            ['label' => __('Reports'), 'icon' => 'fa-solid fa-chart-column', 'color' => '#f59e0b', 'url' => route('branch.assets.reports.summary'), 'active' => $is('branch.assets.reports.*')],
        ]],
        ['label' => __('Examinations'), 'icon' => 'fa-solid fa-file-pen', 'color' => '#ef4444', 'show' => $u->can('examinations.view') || $u->can('marks.enter'), 'children' => [
            ['label' => __('Exams'), 'icon' => 'fa-solid fa-file-signature', 'url' => route('branch.exams.index'), 'active' => $is('branch.exams.index', 'branch.exams.create', 'branch.exams.show', 'branch.exams.edit', 'branch.exams.routine', 'branch.exams.results.*', 'branch.exams.admit-cards', 'branch.exams.seat-plan')],
            ['label' => __('Marks entry'), 'icon' => 'fa-solid fa-pen-to-square', 'color' => '#0ea5e9', 'url' => route('branch.exams.marks.index'), 'active' => $is('branch.exams.marks.*'), 'show' => $u->can('marks.enter') || $u->can('marks.view')],
            ['label' => __('Mark corrections'), 'icon' => 'fa-solid fa-file-circle-check', 'color' => '#8b5cf6', 'url' => route('branch.exams.corrections.index'), 'active' => $is('branch.exams.corrections.*'), 'badge' => $pendingMarkCorrections, 'show' => $u->can('marks.approve-correction') || $u->can('marks.enter')],
            ['label' => __('Grading scales'), 'icon' => 'fa-solid fa-sliders', 'color' => '#f59e0b', 'url' => route('branch.exams.grading.index'), 'active' => $is('branch.exams.grading.*')],
        ]],
        ['label' => __('Fees & accounts'), 'icon' => 'fa-solid fa-sack-dollar', 'color' => '#0f766e', 'show' => $u->can('invoices.view') || $u->can('payments.view') || $u->can('payments.collect') || $u->can('fees.view') || $u->can('accounting.view') || $u->can('expenses.view') || $u->can('payroll.view') || $u->can('financial-reports.view'), 'children' => [
            ['label' => __('Collect fees'), 'icon' => 'fa-solid fa-hand-holding-dollar', 'color' => '#16a34a', 'url' => route('branch.finance.students.search'), 'active' => $is('branch.finance.students.*'), 'show' => $u->can('payments.collect')],
            ['label' => __('Invoices'), 'icon' => 'fa-solid fa-file-invoice', 'url' => route('branch.finance.invoices.index'), 'active' => $is('branch.finance.invoices.index', 'branch.finance.invoices.show', 'branch.finance.invoices.create'), 'show' => $u->can('invoices.view')],
            ['label' => __('Generate invoices'), 'icon' => 'fa-solid fa-bolt', 'color' => '#f59e0b', 'url' => route('branch.finance.invoices.generate'), 'active' => $is('branch.finance.invoices.generate'), 'show' => $u->can('invoices.generate')],
            ['label' => __('Receipts'), 'icon' => 'fa-solid fa-receipt', 'color' => '#0ea5e9', 'url' => route('branch.finance.payments.index'), 'active' => $is('branch.finance.payments.*', 'branch.finance.refunds.*'), 'show' => $u->can('payments.view')],
            ['label' => __('Online payments'), 'icon' => 'fa-solid fa-globe', 'color' => '#db2777', 'url' => route('branch.finance.online-payments.index'), 'active' => $is('branch.finance.online-payments.index', 'branch.finance.online-payments.show'), 'badge' => $onlineReview ?? 0, 'show' => $u->can('payments.reconcile')],
            ['label' => __('Fee reports'), 'icon' => 'fa-solid fa-chart-column', 'color' => '#8b5cf6', 'url' => route('branch.finance.reports.collection'), 'active' => $is('branch.finance.reports.collection', 'branch.finance.reports.dues', 'branch.finance.reports.discounts'), 'show' => $u->can('financial-reports.view')],
            ['label' => __('Expenses'), 'icon' => 'fa-solid fa-money-bill-wave', 'color' => '#dc2626', 'url' => route('branch.finance.expenses.index'), 'active' => $is('branch.finance.expenses.*'), 'show' => $u->can('expenses.view')],
            ['label' => __('Suppliers'), 'icon' => 'fa-solid fa-truck-field', 'color' => '#a16207', 'url' => route('branch.finance.vendors.index'), 'active' => $is('branch.finance.vendors.*'), 'show' => $u->can('expenses.view')],
            ['label' => __('Payroll'), 'icon' => 'fa-solid fa-money-check-dollar', 'color' => '#059669', 'url' => route('branch.finance.payroll.index'), 'active' => $is('branch.finance.payroll.index', 'branch.finance.payroll.show', 'branch.finance.payroll.slips'), 'show' => $u->can('payroll.view')],
            ['label' => __('Salary structures'), 'icon' => 'fa-solid fa-user-tie', 'color' => '#0891b2', 'url' => route('branch.finance.payroll.salaries'), 'active' => $is('branch.finance.payroll.salaries*'), 'show' => $u->can('payroll.view')],
            ['label' => __('Day book & vouchers'), 'icon' => 'fa-solid fa-book', 'color' => '#7c3aed', 'url' => route('branch.finance.journal.index'), 'active' => $is('branch.finance.journal.*'), 'show' => $u->can('accounting.view')],
            ['label' => __('Accounting reports'), 'icon' => 'fa-solid fa-scale-balanced', 'color' => '#2563eb', 'url' => route('branch.finance.reports.trial-balance'), 'active' => $is('branch.finance.reports.ledger', 'branch.finance.reports.trial-balance', 'branch.finance.reports.income-expenditure', 'branch.finance.reports.balance-sheet', 'branch.finance.reports.cash-flow'), 'show' => $u->can('financial-reports.view')],
            ['label' => __('Budget'), 'icon' => 'fa-solid fa-bullseye', 'color' => '#ea580c', 'url' => route('branch.finance.reports.budget'), 'active' => $is('branch.finance.reports.budget*'), 'show' => $u->can('financial-reports.view')],
            ['label' => __('Bank reconciliation'), 'icon' => 'fa-solid fa-building-columns', 'color' => '#0d9488', 'url' => route('branch.finance.reports.reconcile'), 'active' => $is('branch.finance.reports.reconcile*'), 'show' => $u->can('accounting.view')],
            ['label' => __('Financial years'), 'icon' => 'fa-solid fa-calendar-check', 'color' => '#475569', 'url' => route('branch.finance.years.index'), 'active' => $is('branch.finance.years.*'), 'show' => $u->can('accounting.view')],
            ['label' => __('Fee structure'), 'icon' => 'fa-solid fa-table-cells', 'color' => '#14b8a6', 'url' => route('branch.finance.structures.index'), 'active' => $is('branch.finance.structures.*'), 'show' => $u->can('fees.view')],
            ['label' => __('Fee heads'), 'icon' => 'fa-solid fa-tags', 'color' => '#f97316', 'url' => route('branch.finance.heads.index'), 'active' => $is('branch.finance.heads.*'), 'show' => $u->can('fees.view')],
            ['label' => __('Chart of accounts'), 'icon' => 'fa-solid fa-sitemap', 'color' => '#64748b', 'url' => route('branch.finance.accounts.index'), 'active' => $is('branch.finance.accounts.*'), 'show' => $u->can('accounting.view')],
            ['label' => __('Online payment settings'), 'icon' => 'fa-solid fa-plug', 'color' => '#db2777', 'url' => route('branch.finance.online-payments.settings'), 'active' => $is('branch.finance.online-payments.settings*'), 'show' => $u->can('settings.update')],
            ['label' => __('Fee settings'), 'icon' => 'fa-solid fa-sliders', 'color' => '#6366f1', 'url' => route('branch.finance.settings'), 'active' => $is('branch.finance.settings*'), 'show' => $u->can('fees.view')],
        ]],
        ['label' => __('Reports centre'), 'icon' => 'fa-solid fa-chart-line', 'color' => '#8b5cf6', 'show' => $u->can('reports.view'), 'children' => [
            ['label' => __('All reports'), 'icon' => 'fa-solid fa-table-list', 'color' => '#8b5cf6', 'url' => route('branch.reports.index'), 'active' => $is('branch.reports.index', 'branch.reports.show')],
            ['label' => __('My exports'), 'icon' => 'fa-solid fa-file-arrow-down', 'color' => '#0ea5e9', 'url' => route('branch.reports.exports.index'), 'active' => $is('branch.reports.exports.*')],
        ]],
        ['label' => __('Communication'), 'icon' => 'fa-solid fa-bullhorn', 'color' => '#e11d48', 'children' => [
            ['label' => __('Notice board'), 'icon' => 'fa-solid fa-thumbtack', 'color' => '#e11d48', 'url' => route('board.index'), 'active' => $is('board.*')],
            ['label' => __('Manage notices'), 'icon' => 'fa-solid fa-clipboard-list', 'color' => '#f97316', 'url' => route('branch.communication.notices.index'), 'active' => $is('branch.communication.notices.*'), 'show' => $u->can('notices.view')],
            ['label' => __('Send message'), 'icon' => 'fa-solid fa-paper-plane', 'color' => '#0ea5e9', 'url' => route('branch.communication.messages.create'), 'active' => $is('branch.communication.messages.create'), 'show' => $u->can('notifications.send')],
            ['label' => __('Message history'), 'icon' => 'fa-solid fa-list-check', 'color' => '#16a34a', 'url' => route('branch.communication.messages.index'), 'active' => $is('branch.communication.messages.index', 'branch.communication.messages.show'), 'show' => $u->can('notifications.view')],
            ['label' => __('Usage report'), 'icon' => 'fa-solid fa-chart-simple', 'color' => '#8b5cf6', 'url' => route('branch.communication.messages.usage'), 'active' => $is('branch.communication.messages.usage'), 'show' => $u->can('notifications.view')],
            ['label' => __('Message templates'), 'icon' => 'fa-solid fa-file-lines', 'color' => '#6366f1', 'url' => route('branch.communication.templates.index'), 'active' => $is('branch.communication.templates.*'), 'show' => $u->can('notifications.configure')],
            ['label' => __('Opt-outs'), 'icon' => 'fa-solid fa-ban', 'color' => '#64748b', 'url' => route('branch.communication.opt-outs.index'), 'active' => $is('branch.communication.opt-outs.*'), 'show' => $u->can('notifications.view')],
            ['label' => __('SMS, e-mail & WhatsApp settings'), 'icon' => 'fa-solid fa-tower-broadcast', 'color' => '#0f766e', 'url' => route('branch.communication.settings'), 'active' => $is('branch.communication.settings*'), 'show' => $u->can('notifications.configure')],
        ]],
        ['label' => __('Website'), 'icon' => 'fa-solid fa-globe', 'color' => '#0d9488', 'show' => $u->can('website.view'), 'children' => [
            ['label' => __('Site settings'), 'icon' => 'fa-solid fa-image', 'url' => route('branch.website.settings'), 'active' => $is('branch.website.settings*')],
            ['label' => __('Pages'), 'icon' => 'fa-solid fa-file-lines', 'color' => '#0ea5e9', 'url' => route('branch.website.pages.index'), 'active' => $is('branch.website.pages.*')],
            ['label' => __('News & notices'), 'icon' => 'fa-solid fa-newspaper', 'color' => '#f59e0b', 'url' => route('branch.website.posts.index'), 'active' => $is('branch.website.posts.*')],
            ['label' => __('View website'), 'icon' => 'fa-solid fa-arrow-up-right-from-square', 'color' => '#16a34a', 'url' => route('branch.home'), 'blank' => true],
        ]],
        ['label' => __('Settings'), 'icon' => 'fa-solid fa-gear', 'color' => '#64748b', 'children' => [
            ['label' => __('Branch settings'), 'icon' => 'fa-solid fa-sliders', 'url' => route('branch.settings'), 'active' => $is('branch.settings*'), 'show' => $u->can('settings.view')],
            ['label' => __('Switch branch'), 'icon' => 'fa-solid fa-right-left', 'color' => '#14b8a6', 'url' => route('branch.switch-branch'), 'active' => $is('branch.switch-branch*')],
            ['label' => __('Audit log'), 'icon' => 'fa-solid fa-clock-rotate-left', 'color' => '#f59e0b', 'url' => route('branch.audit-logs.index'), 'active' => $is('branch.audit-logs.*'), 'show' => $u->can('audit-logs.view')],
        ]],
    ];
@endphp
