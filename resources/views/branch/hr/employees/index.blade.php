@extends('layouts.branch')
@section('title', __('Employees'))

@section('content')
<x-page-header :title="__('Teachers & employees')" :breadcrumbs="[$currentBranch->name => route('home.redirect'), __('Employees') => null]">
    <x-slot:actions>
        @can('create', App\Models\HR\Employee::class)
            <a href="{{ route('branch.hr.employees.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-person-plus me-1"></i>{{ __('New employee') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name, ID, mobile, e-mail') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm" aria-label="{{ __('Type') }}">
                    <option value="">{{ __('Teachers & staff') }}</option>
                    <option value="teacher" @selected(request('type') === 'teacher')>{{ __('Teachers') }}</option>
                    <option value="staff" @selected(request('type') === 'staff')>{{ __('Other staff') }}</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="department_id" class="form-select form-select-sm" aria-label="{{ __('Department') }}">
                    <option value="">{{ __('All departments') }}</option>
                    @foreach($departments as $id => $name)<option value="{{ $id }}" @selected((int) request('department_id') === $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="designation_id" class="form-select form-select-sm" aria-label="{{ __('Designation') }}">
                    <option value="">{{ __('All designations') }}</option>
                    @foreach($designations as $id => $name)<option value="{{ $id }}" @selected((int) request('designation_id') === $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                    @foreach(array_merge(App\Models\HR\Employee::STATUSES, ['all']) as $st)
                        <option value="{{ $st }}" @selected($status === $st)>{{ __(ucfirst($st)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button></div>
        </form>
    </div>

    @if($employees->isEmpty())
        <x-empty-state icon="person-badge" :title="__('No employees match these filters')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>{{ __('Employee') }}</th><th>{{ __('ID') }}</th><th>{{ __('Designation') }}</th><th>{{ __('Department') }}</th><th>{{ __('Mobile') }}</th><th>{{ __('Login') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($employees as $e)
                    <tr>
                        <td class="d-flex align-items-center gap-2">
                            <span class="avatar">{{ $e->initials() }}</span>
                            <div><a href="{{ route('branch.hr.employees.show', $e) }}" class="fw-semibold text-decoration-none">{{ $e->name }}</a>
                                <div class="small text-body-secondary">{{ $e->employee_type === 'teacher' ? __('Teacher') : __('Staff') }} · {{ __(ucfirst(str_replace('_', ' ', $e->employment_type))) }}</div></div>
                        </td>
                        <td><code>{{ $e->employee_code }}</code></td>
                        <td>{{ $e->designation?->name ?? '—' }}</td>
                        <td>{{ $e->department?->name ?? '—' }}</td>
                        <td>{{ $e->phone }}</td>
                        <td>@if($e->user)<i class="fa-solid fa-circle-check text-success" title="{{ __('Has login') }}"></i>@else<span class="text-body-secondary">—</span>@endif</td>
                        <td><x-status-badge :status="$e->status" /></td>
                        <td class="text-end"><a href="{{ route('branch.hr.employees.show', $e) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Open') }}"><i class="bi bi-chevron-right"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex align-items-center">
            <span class="small text-body-secondary me-auto">{{ __('Total') }}: {{ bn_digits($employees->total()) }}</span>
            {{ $employees->links() }}
        </div>
    @endif
</div>
@endsection
