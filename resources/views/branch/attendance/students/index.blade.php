@extends($layout)
@section('title', __('Student attendance'))

@section('content')
<x-page-header :title="__('Student attendance')" :breadcrumbs="[$currentBranch->name => route('home.redirect'), __('Attendance') => null]">
    <x-slot:actions>
        @if($routePrefix === 'branch.')
            @can('student-attendance.mark')
                <a href="{{ route('branch.attendance.scan') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-qrcode me-1"></i>{{ __('Scan station') }}</a>
            @endcan
            @can('student-attendance.view')
                <a href="{{ route('branch.attendance.reports.students') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-table-cells me-1"></i>{{ __('Monthly sheet') }}</a>
            @endcan
        @endif
    </x-slot:actions>
</x-page-header>

<form method="GET" class="panel panel-body mb-4 d-flex flex-wrap align-items-end gap-2">
    <div><label class="form-label small mb-1" for="date">{{ __('Date') }}</label>
        <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
    <button class="btn btn-brand btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Show') }}</button>
    <div class="ms-auto small text-body-secondary">{{ __('Session') }}: <strong>{{ $session?->name ?? __('No current session') }}</strong></div>
</form>

@if($isHoliday)
    <div class="alert alert-info"><i class="fa-solid fa-umbrella-beach me-1"></i> {{ __(':date is a weekly holiday.', ['date' => $date->format('d M Y')]) }}</div>
@endif

@php
    $tot = ['enrolled' => 0, 'present' => 0, 'absent' => 0, 'taken' => 0];
    foreach ($sections as $s) {
        $c = $counts[$s->id] ?? [];
        $tot['enrolled'] += (int) ($enrolled[$s->id] ?? 0);
        $tot['present'] += ($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['half_day'] ?? 0);
        $tot['absent'] += $c['absent'] ?? 0;
        $tot['taken'] += $c ? 1 : 0;
    }
@endphp
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Enrolled')" :value="bn_digits($tot['enrolled'])" icon="fa-solid fa-user-graduate" bg="#bee8fb" color="#0369a1" :sub="__('current session')" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Present')" :value="bn_digits($tot['present'])" icon="fa-solid fa-user-check" bg="#bfead6" color="#15803d" :sub="__('incl. late & half-day')" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Absent')" :value="bn_digits($tot['absent'])" icon="fa-solid fa-user-xmark" bg="#fbd0d0" color="#b91c1c" :sub="$date->format('d M Y')" /></div>
    <div class="col-6 col-xl-3"><x-stat-card :label="__('Registers taken')" :value="bn_digits($tot['taken']).' / '.bn_digits($sections->count())" icon="fa-solid fa-clipboard-list" bg="#d4c5f2" color="#6d28d9" :sub="__('sections')" /></div>
</div>

<div class="card">
    @if($sections->isEmpty())
        <x-empty-state icon="clipboard-x" :title="$routePrefix === 'branch.' ? __('No active sections') : __('You are not class teacher of any section')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Class') }}</th><th>{{ __('Section') }}</th><th>{{ __('Class teacher') }}</th><th>{{ __('Enrolled') }}</th>
                    <th>{{ __('Present') }}</th><th>{{ __('Absent') }}</th><th>{{ __('Other') }}</th><th>%</th><th>{{ __('Register') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($sections as $s)
                    @php
                        $c = $counts[$s->id] ?? [];
                        $pct = App\Enums\AttendanceStatus::percentage($c);
                        $present = ($c['present'] ?? 0) + ($c['late'] ?? 0) + ($c['half_day'] ?? 0);
                        $other = ($c['leave'] ?? 0) + ($c['excused'] ?? 0);
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $s->schoolClass?->name }}</td>
                        <td>{{ $s->name }}</td>
                        <td class="small">{{ $s->classTeacher?->name ?? '—' }}</td>
                        <td>{{ bn_digits($enrolled[$s->id] ?? 0) }}</td>
                        <td class="text-success">{{ $c ? bn_digits($present) : '—' }}</td>
                        <td class="text-danger">{{ $c ? bn_digits($c['absent'] ?? 0) : '—' }}</td>
                        <td>{{ $c ? bn_digits($other) : '—' }}</td>
                        <td>{{ $pct === null ? '—' : bn_digits($pct).'%' }}</td>
                        <td>@if($c)<span class="badge text-bg-success">{{ __('Taken') }}</span>@else<span class="badge text-bg-warning">{{ __('Not taken') }}</span>@endif</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route($routePrefix.'attendance.students.register', [$s, 'date' => $date->toDateString()]) }}">{{ __('Open') }} <i class="bi bi-chevron-right"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
