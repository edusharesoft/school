@extends('layouts.branch')
@section('title', __('Dashboard'))

@section('content')
<x-page-header :title="__('Branch dashboard')" :breadcrumbs="[$currentBranch->name => null]" />

<form method="GET" class="panel panel-body mb-4 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="from">{{ __('From') }}</label>
            <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="to">{{ __('To') }}</label>
            <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-md-3"><button class="btn btn-brand btn-sm"><i class="bi bi-funnel me-1"></i>{{ __('Apply') }}</button></div>
    </div>
</form>

<div data-dashboard="{{ route('branch.dashboard.data') }}" data-empty-text="{{ __('No data for the selected period.') }}" data-error-text="{{ __('Could not load dashboard data. Please retry.') }}">
    <div class="alert alert-danger d-none" data-dashboard-error>{{ __('Could not load dashboard data. Please refresh the page.') }}</div>

    @php
        $studentsUrl = auth()->user()->can('viewAny', App\Models\Students\Student::class) ? route('branch.students.index') : null;
        $attUrl = auth()->user()->can('student-attendance.view') ? route('branch.attendance.students.index') : null;
        $staffAttUrl = auth()->user()->can('staff-attendance.view') ? route('branch.attendance.staff.index') : null;
        $cards = [
            ['students_active', __('Active students'), 'fa-solid fa-user-graduate', '#bee8fb', '#0369a1', __('enrolled & active'), $studentsUrl],
            ['students_present_today', __('Students present today'), 'fa-solid fa-user-check', '#bfead6', '#15803d', __('incl. late & half-day'), $attUrl],
            ['students_absent_today', __('Students absent today'), 'fa-solid fa-user-xmark', '#fbd0d0', '#b91c1c', __('daily registers'), $attUrl],
            ['student_attendance_pct_today', __('Attendance % today'), 'fa-solid fa-percent', '#fde6c2', '#c2410c', __('students'), $attUrl],
            ['teachers_total', __('Teachers'), 'fa-solid fa-chalkboard-user', '#d4c5f2', '#6d28d9', __('active'), auth()->user()->can('viewAny', App\Models\HR\Employee::class) ? route('branch.hr.employees.index', ['type' => 'teacher']) : null],
            ['teachers_present_today', __('Teachers present today'), 'fa-solid fa-person-chalkboard', '#bfead6', '#047857', __('checked in / marked'), $staffAttUrl],
            ['teachers_absent_today', __('Teachers absent today'), 'fa-solid fa-user-slash', '#fbd7f0', '#c026d3', __('marked absent'), $staffAttUrl],
            ['employees_total', __('All employees'), 'fa-solid fa-id-badge', '#f3c9f9', '#a21caf', __('teachers + staff'), auth()->user()->can('viewAny', App\Models\HR\Employee::class) ? route('branch.hr.employees.index') : null],
            ['new_admissions', __('New admissions (period)'), 'fa-solid fa-user-plus', '#bfd7fd', '#1d4ed8', $filters['from'].' → '.$filters['to'], $studentsUrl],
            ['pending_leaves', __('Pending leave'), 'fa-solid fa-plane-departure', '#bee8fb', '#0e7490', __('awaiting approval'), auth()->user()->can('leaves.approve') ? route('branch.leaves.index') : null],
            ['open_absence_alerts', __('Absence alerts'), 'fa-solid fa-triangle-exclamation', '#fde6c2', '#b45309', __('consecutive absences'), $attUrl ? route('branch.attendance.alerts') : null],
            ['failed_logins_today', __('Failed sign-ins today'), 'fa-solid fa-shield-halved', '#e2e8f0', '#475569', __('watch for attacks'), null],
        ];
    @endphp
    <div class="row g-4 mb-4">
        @foreach ($cards as [$key, $label, $icon, $bg, $color, $sub, $link])
            <div class="col-6 col-md-4 col-xl-2">
                <x-stat-card :label="$label" :card="$key" :icon="$icon" :bg="$bg" :color="$color" :sub="$sub" :href="$link" />
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8"><x-chart-card :title="__('Daily attendance')" :subtitle="$filters['from'].' → '.$filters['to']" chart="attendance_trend" color="green" icon="fa-solid fa-chart-line" /></div>
        <div class="col-xl-4"><x-chart-card :title="__('Teachers & staff today')" chart="staff_status_today" color="purple" icon="fa-solid fa-user-tie" /></div>
    </div>
    <div class="mb-4"><x-chart-card :title="__('Class-wise attendance today (%)')" chart="class_attendance" color="blue" icon="fa-solid fa-chalkboard" :height="260" /></div>
    @if(auth()->user()->can('payments.view') || auth()->user()->can('financial-reports.view'))
        @php($finUrl = auth()->user()->can('financial-reports.view') ? route('branch.finance.reports.collection') : null)
        <div class="row g-4 mb-4">
            <div class="col-xl-5">
                <div class="row g-4">
                    <div class="col-6"><x-stat-card :label="__('Fees collected today')" card="fees_collected_today" icon="fa-solid fa-hand-holding-dollar" bg="#bfead6" color="#15803d" :sub="__('receipts, ৳')" :href="$finUrl" /></div>
                    <div class="col-6"><x-stat-card :label="__('Collected this month')" card="fees_collected_month" icon="fa-solid fa-sack-dollar" bg="#bee8fb" color="#0369a1" :sub="__('receipts, ৳')" :href="$finUrl" /></div>
                    <div class="col-6"><x-stat-card :label="__('Outstanding fees')" card="fees_outstanding" icon="fa-solid fa-file-invoice-dollar" bg="#fbd0d0" color="#b91c1c" :sub="__('open invoices, ৳')" :href="auth()->user()->can('financial-reports.view') ? route('branch.finance.reports.dues') : null" /></div>
                    <div class="col-6"><x-stat-card :label="__('Overdue invoices')" card="invoices_overdue" icon="fa-solid fa-triangle-exclamation" bg="#fde6c2" color="#b45309" :sub="__('past the due date')" :href="auth()->user()->can('invoices.view') ? route('branch.finance.invoices.index', ['status' => 'overdue']) : null" /></div>
                </div>
            </div>
            <div class="col-xl-7"><x-chart-card :title="__('Fees billed vs collected')" :subtitle="__('Last 6 months')" chart="fee_collection" color="green" icon="fa-solid fa-chart-column" :height="250" /></div>
        </div>
    @endif
    @php($lastExam = auth()->user()->can('results.view') ? App\Models\Exams\Examination::query()->where('status', 'published')->latest('published_at')->value('name') : null)
    @if($lastExam)
        <div class="row g-4 mb-4">
            <div class="col-xl-5"><x-chart-card :title="__('Grades — latest published exam')" :subtitle="$lastExam" chart="exam_grades" color="orange" icon="fa-solid fa-award" /></div>
            <div class="col-xl-7"><x-chart-card :title="__('Pass rate by class (%)')" :subtitle="$lastExam" chart="exam_pass_by_class" color="green" icon="fa-solid fa-square-poll-vertical" /></div>
        </div>
    @endif
    <div class="row g-4 mb-4">
        <div class="col-xl-6"><x-chart-card :title="__('Students by class (current session)')" chart="students_by_class" color="yellow" icon="fa-solid fa-chalkboard" /></div>
        <div class="col-xl-6"><x-chart-card :title="__('Admission trend')" :subtitle="$filters['from'].' → '.$filters['to']" chart="admission_trend" color="green" icon="fa-solid fa-chart-line" /></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-8"><x-chart-card :title="__('Sign-in activity')" chart="login_trend" color="purple" icon="fa-solid fa-right-to-bracket" /></div>
        <div class="col-xl-4"><x-chart-card :title="__('Accounts by type')" chart="accounts_by_type" color="pink" icon="fa-solid fa-users" /></div>
    </div>

    <div class="dp blue">
        <div class="dp-head">
            <h2 style="font-size:16px"><i class="fa-solid fa-clock-rotate-left me-1 text-primary"></i> {{ __('Recent activity in this branch') }}</h2>
            <span class="sub">{{ __('Updated') }}: <span data-generated>—</span></span>
        </div>
        <div class="dp-body pt-0" data-activity></div>
    </div>
</div>
@endsection
