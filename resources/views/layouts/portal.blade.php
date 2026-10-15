{{-- Teacher / Student / Parent portals — same panel design as the back-office, with a portal-specific menu. --}}
@extends('layouts.shell')

@php
    $u = auth()->user();
    $is = fn (...$r) => request()->routeIs(...$r);
    $b = $currentBranch;
    $portal = $u->portal();
    $brand = [
        'home' => route('home.redirect'),
        'name' => $b?->displayName(),
        'sub' => __(ucfirst($portal).' portal'),
        'logo' => \App\Support\AuthTheme::logo($b),
        'icon' => match ($portal) { 'teacher' => 'fa-solid fa-chalkboard-user', 'parent' => 'fa-solid fa-people-roof', default => 'fa-solid fa-user-graduate' },
        'gradient' => 'linear-gradient(135deg,#0ea5e9,#6366f1)',
        'badge' => $b?->code,
    ];
    $notificationsUrl = null;
    $home = ['label' => __('Home'), 'icon' => 'fa-solid fa-house', 'color' => '#6366f1', 'url' => route('portal.'.$portal), 'active' => $is('portal.'.$portal)];
    $notices = ['label' => __('Notice board'), 'icon' => 'fa-solid fa-thumbtack', 'color' => '#e11d48', 'url' => route('board.index'), 'active' => $is('board.*')];
    $inbox = ['label' => __('Notifications'), 'icon' => 'fa-solid fa-bell', 'color' => '#f59e0b', 'url' => route('inbox.index'), 'active' => $is('inbox.*')];
    $prefs = ['label' => __('Message preferences'), 'icon' => 'fa-solid fa-sliders', 'color' => '#64748b', 'url' => route('preferences.edit'), 'active' => $is('preferences.*')];
    $menu = match ($portal) {
        'teacher' => [
            $home,
            ['label' => __('Attendance'), 'icon' => 'fa-solid fa-clipboard-check', 'color' => '#16a34a', 'children' => [
                ['label' => __('My class registers'), 'icon' => 'fa-solid fa-user-check', 'url' => route('portal.teacher.attendance.students.index'), 'active' => $is('portal.teacher.attendance.students.*'), 'show' => $u->can('student-attendance.mark')],
                ['label' => __('Correction requests'), 'icon' => 'fa-solid fa-pen-to-square', 'color' => '#f59e0b', 'url' => route('portal.teacher.attendance.corrections.index'), 'active' => $is('portal.teacher.attendance.corrections.*'), 'show' => $u->can('student-attendance.correct')],
            ]],
            ['label' => __('Leave applications'), 'icon' => 'fa-solid fa-plane-departure', 'color' => '#0ea5e9', 'url' => route('portal.teacher.leaves.index'), 'active' => $is('portal.teacher.leaves.*'), 'show' => $u->can('leaves.view')],
            ['label' => __('My routine'), 'icon' => 'fa-solid fa-calendar-week', 'color' => '#8b5cf6', 'url' => route('portal.teacher.academics.timetable.teacher'), 'active' => $is('portal.teacher.academics.timetable.*')],
            ['label' => __('Marks entry'), 'icon' => 'fa-solid fa-file-pen', 'color' => '#ef4444', 'url' => route('portal.teacher.exams.marks.index'), 'active' => $is('portal.teacher.exams.*'), 'show' => $u->can('marks.enter')],
            ['label' => __('Learning'), 'icon' => 'fa-solid fa-book-open-reader', 'color' => '#f97316', 'show' => $u->can('homework.view') || $u->can('assignments.view') || $u->can('materials.view') || $u->can('online-classes.view') || $u->can('quizzes.view'), 'children' => [
                ['label' => __('Homework & assignments'), 'icon' => 'fa-solid fa-book', 'url' => route('portal.teacher.learning.assignments.index'), 'active' => $is('portal.teacher.learning.assignments.*'), 'show' => $u->can('homework.view') || $u->can('assignments.view')],
                ['label' => __('Class materials'), 'icon' => 'fa-solid fa-folder-open', 'color' => '#0ea5e9', 'url' => route('portal.teacher.learning.materials.index'), 'active' => $is('portal.teacher.learning.materials.*'), 'show' => $u->can('materials.view')],
                ['label' => __('Online & offline classes'), 'icon' => 'fa-solid fa-video', 'color' => '#6366f1', 'url' => route('portal.teacher.learning.online-classes.index'), 'active' => $is('portal.teacher.learning.online-classes.*'), 'show' => $u->can('online-classes.view')],
                ['label' => __('Quizzes'), 'icon' => 'fa-solid fa-circle-question', 'color' => '#ec4899', 'url' => route('portal.teacher.learning.quizzes.index'), 'active' => $is('portal.teacher.learning.quizzes.*'), 'show' => $u->can('quizzes.view')],
                ['label' => __('Learning progress'), 'icon' => 'fa-solid fa-chart-line', 'color' => '#16a34a', 'url' => route('portal.teacher.learning.progress.index'), 'active' => $is('portal.teacher.learning.progress.*'), 'show' => $u->can('homework.view') || $u->can('assignments.view')],
            ]],
            $notices,
            $inbox,
        ],
        'student' => [
            $home,
            ['label' => __('Results'), 'icon' => 'fa-solid fa-square-poll-vertical', 'color' => '#ef4444', 'url' => route('portal.student.results'), 'active' => $is('portal.student.results*')],
            ['label' => __('Homework & progress'), 'icon' => 'fa-solid fa-book', 'color' => '#f97316', 'url' => route('portal.student.learning'), 'active' => $is('portal.student.learning', 'portal.student.assignments.*')],
            ['label' => __('Quizzes'), 'icon' => 'fa-solid fa-circle-question', 'color' => '#ec4899', 'url' => route('portal.student.quizzes'), 'active' => $is('portal.student.quizzes*')],
            ['label' => __('Online classes'), 'icon' => 'fa-solid fa-video', 'color' => '#6366f1', 'url' => route('portal.student.online-classes'), 'active' => $is('portal.student.online-classes')],
            ['label' => __('Learning materials'), 'icon' => 'fa-solid fa-folder-open', 'color' => '#0ea5e9', 'url' => route('portal.student.materials'), 'active' => $is('portal.student.materials*')],
            ['label' => __('Library'), 'icon' => 'fa-solid fa-book-bookmark', 'color' => '#0d9488', 'url' => route('portal.student.library'), 'active' => $is('portal.student.library*')],
            ['label' => __('Transport & hostel'), 'icon' => 'fa-solid fa-bus', 'color' => '#7c3aed', 'url' => route('portal.student.facilities'), 'active' => $is('portal.student.facilities')],
            ['label' => __('Fees'), 'icon' => 'fa-solid fa-receipt', 'color' => '#0f766e', 'url' => route('portal.student.fees'), 'active' => $is('portal.student.fees*')],
            $notices,
            $inbox,
            $prefs,
        ],
        default => [
            $home,
            ['label' => __('Children & results'), 'icon' => 'fa-solid fa-children', 'color' => '#0ea5e9', 'url' => route('portal.parent'), 'active' => $is('portal.parent.results*')],
            ['label' => __('Homework & learning'), 'icon' => 'fa-solid fa-book', 'color' => '#f97316', 'url' => route('portal.parent'), 'active' => $is('portal.parent.learning', 'portal.parent.assignments.*', 'portal.parent.materials*')],
            ['label' => __('Fees & payments'), 'icon' => 'fa-solid fa-receipt', 'color' => '#0f766e', 'url' => route('portal.parent'), 'active' => $is('portal.parent.fees*')],
            $notices,
            $inbox,
            $prefs,
        ],
    };
@endphp
