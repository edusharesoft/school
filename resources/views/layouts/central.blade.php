@extends('layouts.shell')

@php
    $u = auth()->user();
    $is = fn (...$r) => request()->routeIs(...$r);
    $institution = $u->institution;
    $logo = \App\Support\AuthTheme::logo(null, $institution);
    $brand = [
        'home' => route('central.dashboard'),
        'name' => $u->isPlatformOwner() ? __('Super Admin') : ($institution?->displayName() ?? config('app.name')),
        'sub' => $u->isPlatformOwner() ? \App\Support\AuthTheme::name() : __('Central Administration'),
        'logo' => $logo,
        'icon' => 'fa-solid fa-crown',
        'gradient' => 'linear-gradient(135deg,#f59e0b,#ef4444)',
    ];
    $notificationsUrl = $u->can('audit-logs.view') ? route('central.audit-logs.index') : null;
    $menu = [
        ['label' => __('Dashboard'), 'icon' => 'fa-solid fa-gauge-high', 'color' => '#6366f1', 'url' => route('central.dashboard'), 'active' => $is('central.dashboard'), 'show' => $u->can('dashboard.central')],
        ['sec' => __('Institution')],
        ['label' => __('Institutions'), 'icon' => 'fa-solid fa-building-columns', 'color' => '#0ea5e9', 'url' => route('central.institutions.index'), 'active' => $is('central.institutions.*'), 'show' => $u->can('viewAny', App\Models\Institution::class)],
        ['label' => __('Branches'), 'icon' => 'fa-solid fa-school', 'color' => '#16a34a', 'url' => route('central.branches.index'), 'active' => $is('central.branches.*'), 'show' => $u->can('viewAny', App\Models\Branch::class)],
        ['label' => __('Academic years'), 'icon' => 'fa-solid fa-calendar-days', 'color' => '#f59e0b', 'url' => route('central.academic-years.index'), 'active' => $is('central.academic-years.*'), 'show' => $u->can('viewAny', App\Models\Academic\AcademicYear::class)],
        ['label' => __('Institution notices'), 'icon' => 'fa-solid fa-bullhorn', 'color' => '#e11d48', 'url' => route('central.notices.index'), 'active' => $is('central.notices.*'), 'show' => $u->can('notices.view')],
        ['label' => __('Consolidated finance'), 'icon' => 'fa-solid fa-chart-pie', 'color' => '#0f766e', 'url' => route('central.finance.index'), 'active' => $is('central.finance.*'), 'show' => $u->can('financial-reports.view')],
        ['label' => __('Branch-wise inventory'), 'icon' => 'fa-solid fa-boxes-stacked', 'color' => '#b45309', 'url' => route('central.inventory.index'), 'active' => $is('central.inventory.*'), 'show' => $u->can('inventory.view')],
        ['label' => __('Branch-wise assets'), 'icon' => 'fa-solid fa-computer', 'color' => '#0e7490', 'url' => route('central.assets.index'), 'active' => $is('central.assets.*'), 'show' => $u->can('assets.view')],
        ['label' => __('Reports centre'), 'icon' => 'fa-solid fa-chart-line', 'color' => '#8b5cf6', 'url' => route('central.reports.index'), 'active' => $is('central.reports.*'), 'show' => $u->can('reports.view')],
        ['label' => __('Subscriptions'), 'icon' => 'fa-solid fa-credit-card', 'color' => '#db2777', 'show' => $u->can('subscriptions.view'), 'children' => [
            ['label' => __('Institutions & plans'), 'icon' => 'fa-solid fa-building-circle-check', 'url' => route('central.subscriptions.index'), 'active' => $is('central.subscriptions.*')],
            ['label' => __('Plans'), 'icon' => 'fa-solid fa-tags', 'url' => route('central.plans.index'), 'active' => $is('central.plans.*')],
        ]],
        ['label' => __('Open a branch'), 'icon' => 'fa-solid fa-right-left', 'color' => '#14b8a6', 'url' => route('central.switch-branch'), 'active' => $is('central.switch-branch*')],
        ['sec' => __('Access control')],
        ['label' => __('Users & Roles'), 'icon' => 'fa-solid fa-users-gear', 'color' => '#8b5cf6', 'children' => [
            ['label' => __('Users'), 'icon' => 'fa-solid fa-users', 'url' => route('central.users.index'), 'active' => $is('central.users.*'), 'show' => $u->can('viewAny', App\Models\User::class)],
            ['label' => __('Staff assignments'), 'icon' => 'fa-solid fa-people-arrows', 'color' => '#0ea5e9', 'url' => route('central.staff-assignments.index'), 'active' => $is('central.staff-assignments.*'), 'show' => $u->can('employees.view')],
            ['label' => __('Roles & permissions'), 'icon' => 'fa-solid fa-shield-halved', 'color' => '#ef4444', 'url' => route('central.roles.index'), 'active' => $is('central.roles.*'), 'show' => $u->can('viewAny', Spatie\Permission\Models\Role::class)],
        ]],
        ['label' => __('Website'), 'icon' => 'fa-solid fa-globe', 'color' => '#0d9488', 'show' => $u->can('website.view'), 'children' => [
            ['label' => __('Home page settings'), 'icon' => 'fa-solid fa-image', 'url' => route('central.website.settings'), 'active' => $is('central.website.settings*')],
            ['label' => __('Pages'), 'icon' => 'fa-solid fa-file-lines', 'color' => '#0ea5e9', 'url' => route('central.website.pages.index'), 'active' => $is('central.website.pages.*')],
            ['label' => __('News & notices'), 'icon' => 'fa-solid fa-newspaper', 'color' => '#f59e0b', 'url' => route('central.website.posts.index'), 'active' => $is('central.website.posts.*')],
            ['label' => __('Newsletter subscribers'), 'icon' => 'fa-solid fa-envelope-open-text', 'color' => '#8b5cf6', 'url' => route('central.website.subscribers'), 'active' => $is('central.website.subscribers*')],
        ]],
        ['sec' => __('Coming in next parts')],
        ['label' => __('Attendance & exams'), 'icon' => 'fa-solid fa-clipboard-check', 'color' => '#0ea5e9', 'url' => '#', 'soon' => '3B–C'],
        ['label' => __('Fees & accounts'), 'icon' => 'fa-solid fa-sack-dollar', 'color' => '#16a34a', 'url' => '#', 'soon' => '4'],
        ['label' => __('Communication'), 'icon' => 'fa-solid fa-comment-sms', 'color' => '#14b8a6', 'url' => '#', 'soon' => '5'],
        ['sec' => __('System')],
        ['label' => __('System'), 'icon' => 'fa-solid fa-gear', 'color' => '#64748b', 'children' => [
            ['label' => __('Settings'), 'icon' => 'fa-solid fa-sliders', 'url' => route('central.settings'), 'active' => $is('central.settings*'), 'show' => $u->can('settings.view')],
            ['label' => __('Backups'), 'icon' => 'fa-solid fa-database', 'color' => '#0ea5e9', 'url' => route('central.backups.index'), 'active' => $is('central.backups.*'), 'show' => $u->isPlatformOwner() && $u->can('backups.view')],
            ['label' => __('System health'), 'icon' => 'fa-solid fa-heart-pulse', 'color' => '#16a34a', 'url' => route('central.system.health'), 'active' => $is('central.system.*'), 'show' => $u->isPlatformOwner() && $u->can('backups.view')],
            ['label' => __('Audit log'), 'icon' => 'fa-solid fa-clock-rotate-left', 'color' => '#f59e0b', 'url' => route('central.audit-logs.index'), 'active' => $is('central.audit-logs.*'), 'show' => $u->can('audit-logs.view')],
        ]],
        ['label' => __('View website'), 'icon' => 'fa-solid fa-arrow-up-right-from-square', 'color' => '#0ea5e9', 'url' => route('website.home'), 'blank' => true],
    ];
@endphp
