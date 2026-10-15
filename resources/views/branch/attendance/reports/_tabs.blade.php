<ul class="nav nav-pills mb-3 no-print">
    @can('student-attendance.view')
        <li class="nav-item"><a @class(['nav-link', 'active' => request()->routeIs('branch.attendance.reports.students')]) href="{{ route('branch.attendance.reports.students', ['month' => $month->format('Y-m')]) }}"><i class="fa-solid fa-user-graduate me-1"></i>{{ __('Students') }}</a></li>
    @endcan
    @can('staff-attendance.view')
        <li class="nav-item"><a @class(['nav-link', 'active' => request()->routeIs('branch.attendance.reports.staff')]) href="{{ route('branch.attendance.reports.staff', ['month' => $month->format('Y-m')]) }}"><i class="fa-solid fa-user-tie me-1"></i>{{ __('Teachers & staff') }}</a></li>
    @endcan
</ul>
