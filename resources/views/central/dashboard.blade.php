@extends('layouts.central')
@section('title', __('Dashboard'))

@section('content')
<x-page-header :title="__('Institution dashboard')" :breadcrumbs="[__('Central') => null]">
    <x-slot:actions>
        @can('dashboard.export')
            <a href="{{ route('central.dashboard.export', request()->only('branch_id', 'from', 'to')) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-filetype-csv me-1"></i>{{ __('Export CSV') }}
            </a>
        @endcan
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button>
    </x-slot:actions>
</x-page-header>

<form method="GET" class="panel panel-body mb-4 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-sm-6 col-lg-4">
            <label class="form-label small mb-1" for="branch_id">{{ __('Branch') }}</label>
            <select name="branch_id" id="branch_id" class="form-select form-select-sm">
                <option value="">{{ __('All branches') }}</option>
                @foreach($branches as $b)
                    <option value="{{ $b->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $b->id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label small mb-1" for="from">{{ __('From') }}</label>
            <input type="date" name="from" id="from" value="{{ $filters['from'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label small mb-1" for="to">{{ __('To') }}</label>
            <input type="date" name="to" id="to" value="{{ $filters['to'] }}" class="form-control form-control-sm">
        </div>
        <div class="col-lg-4 d-flex gap-2">
            <button class="btn btn-brand btn-sm"><i class="bi bi-funnel me-1"></i>{{ __('Apply') }}</button>
            <a href="{{ route('central.dashboard') }}" class="btn btn-outline-secondary btn-sm">{{ __('Reset') }}</a>
        </div>
    </div>
    <div class="small text-body-secondary mt-2">
        {{ __('Academic year, class and section filters become available with the academic module (Part 3).') }}
    </div>
</form>

<div data-dashboard="{{ route('central.dashboard.data') }}" data-empty-text="{{ __('No data for the selected period.') }}" data-error-text="{{ __('Could not load dashboard data. Please retry.') }}">
    <div class="alert alert-danger d-none" data-dashboard-error>{{ __('Could not load dashboard data. Please refresh the page.') }}</div>

    @php
        $cards = [
            ['branches_total', __('Total branches'), 'fa-solid fa-school', '#bfd7fd', '#1d4ed8', __('all campuses'), route('central.branches.index')],
            ['branches_active', __('Active branches'), 'fa-solid fa-circle-check', '#c0ead7', '#047857', __('online now'), route('central.branches.index', ['status' => 'active'])],
            ['students_active', __('Active students'), 'fa-solid fa-user-graduate', '#bee8fb', '#0369a1', __('enrolled & active'), null],
            ['new_admissions', __('New admissions (period)'), 'fa-solid fa-user-plus', '#bfead6', '#15803d', $filters['from'].' → '.$filters['to'], null],
            ['students_present_today', __('Students present today'), 'fa-solid fa-user-check', '#bfead6', '#15803d', __('incl. late & half-day'), null],
            ['students_absent_today', __('Students absent today'), 'fa-solid fa-user-xmark', '#fbd0d0', '#b91c1c', __('daily registers'), null],
            ['student_attendance_pct_today', __('Attendance % today'), 'fa-solid fa-percent', '#fde6c2', '#c2410c', __('students'), null],
            ['teachers_total', __('Teachers'), 'fa-solid fa-chalkboard-user', '#d4c5f2', '#6d28d9', __('active'), null],
            ['teachers_present_today', __('Teachers present today'), 'fa-solid fa-person-chalkboard', '#bfead6', '#047857', __('checked in / marked'), null],
            ['teachers_absent_today', __('Teachers absent today'), 'fa-solid fa-user-slash', '#fbd7f0', '#c026d3', __('marked absent'), null],
            ['employees_total', __('All employees'), 'fa-solid fa-id-badge', '#f3c9f9', '#a21caf', __('teachers + staff'), null],
            ['failed_logins_today', __('Failed sign-ins today'), 'fa-solid fa-shield-halved', '#fbd0d0', '#b91c1c', __('watch for attacks'), null],
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
        <div class="col-xl-6"><x-chart-card :title="__('Branch-wise attendance today (%)')" :subtitle="__('Click a bar to drill down into that branch')" chart="branch_attendance" color="green" icon="fa-solid fa-clipboard-check" /></div>
        <div class="col-xl-6"><x-chart-card :title="__('Daily attendance')" :subtitle="$filters['from'].' → '.$filters['to']" chart="attendance_trend" color="blue" icon="fa-solid fa-chart-line" /></div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-xl-6"><x-chart-card :title="__('Active students by branch')" :subtitle="__('Click a bar to drill down into that branch')" chart="branch_students" color="yellow" icon="fa-solid fa-user-graduate" /></div>
        <div class="col-xl-6"><x-chart-card :title="__('Admission trend')" :subtitle="$filters['from'].' → '.$filters['to']" chart="admission_trend" color="green" icon="fa-solid fa-chart-line" /></div>
    </div>
    <div class="mb-4"><x-chart-card :title="__('Accounts by branch')" :subtitle="__('Click a bar to drill down into that branch')" chart="branch_accounts" color="pink" icon="fa-solid fa-users" /></div>
    <div class="mb-4"><x-chart-card :title="__('Sign-in activity')" :subtitle="$filters['from'].' → '.$filters['to']" chart="login_trend" color="purple" icon="fa-solid fa-right-to-bracket" /></div>
    <div class="row g-4 mb-4">
        <div class="col-xl-6"><x-chart-card :title="__('Branch status')" chart="branch_status" color="blue" icon="fa-solid fa-circle-half-stroke" :height="260" /></div>
        <div class="col-xl-6"><x-chart-card :title="__('Departments per branch')" chart="departments" color="orange" icon="fa-solid fa-sitemap" :height="260" /></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="dp blue">
                <div class="dp-head">
                    <h2 style="font-size:16px"><i class="fa-solid fa-clock-rotate-left me-1 text-primary"></i> {{ __('Recent activity') }}</h2>
                    @can('audit-logs.view')<a href="{{ route('central.audit-logs.index') }}" class="small">{{ __('View all') }}</a>@endcan
                </div>
                <div class="dp-body pt-0" data-activity><div class="py-4 text-center"><div class="spinner-border spinner-border-sm"></div></div></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="dp orange">
                <div class="dp-head"><h2 style="font-size:16px"><i class="fa-solid fa-road me-1 text-warning"></i> {{ __('Module roll-out') }}</h2></div>
                <div class="dp-body small">
                    <p class="text-body-secondary">{{ __('Attendance, admissions, fee collection, outstanding dues, expenses and examination statistics are added to this dashboard as their modules are delivered:') }}</p>
                    <ul class="mb-0">
                        <li>{{ __('Part 3B-2 — online admissions, transfers, certificates') }}</li>
                        <li>{{ __('Part 3C — examinations & results') }}</li>
                        <li>{{ __('Part 4 — fees, collections, outstanding, expenses') }}</li>
                    </ul>
                </div>
                <div class="px-3 pb-2 small text-body-secondary">{{ __('Updated') }}: <span data-generated>—</span></div>
            </div>
        </div>
    </div>
</div>
@endsection
