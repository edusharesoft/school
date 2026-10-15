@extends('layouts.branch')
@section('title', $employee->name)

@php
    $u = auth()->user();
    $canUpdate = $u->can('update', $employee);
    $row = fn ($label, $value) => '<div class="col-sm-6 col-lg-4"><div class="small text-body-secondary">'.e($label).'</div><div class="fw-medium">'.($value !== null && $value !== '' ? e($value) : '—').'</div></div>';
@endphp

@section('content')
<x-page-header :title="$employee->name" :breadcrumbs="[__('Employees') => route('branch.hr.employees.index'), $employee->employee_code => null]">
    <x-slot:actions>
        @if($canUpdate)
            <a href="{{ route('branch.hr.employees.edit', $employee) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>
        @endif
        @if($employee->isActive())@can('id-cards.issue')
            <a href="{{ route('branch.idcards.employee', $employee) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener"><i class="bi bi-person-vcard me-1"></i>{{ __('ID card') }}</a>
        @endcan @endif
        @can('staff-certificates.issue')
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#staffCertModal"><i class="fa-solid fa-award me-1"></i>{{ __('Certificate') }}</button>
        @endcan
        @can('changeStatus', $employee)
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal"><i class="bi bi-person-gear me-1"></i>{{ __('Change status') }}</button>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel panel-body text-center mb-4">
            @if($employee->photo_path)
                <img src="{{ route('branch.hr.employees.photo', $employee) }}" alt="" class="rounded-circle mb-2" style="width:96px;height:96px;object-fit:cover">
            @else
                <span class="avatar mb-2" style="width:96px;height:96px;font-size:2rem">{{ $employee->initials() }}</span>
            @endif
            <h2 class="h5 mb-0">{{ $employee->name }}</h2>
            <div class="bn text-body-secondary">{{ $employee->name_bn }}</div>
            <div class="mt-1"><code>{{ $employee->employee_code }}</code></div>
            <div class="small mt-1">{{ $employee->designation?->name ?? '—' }} · {{ $employee->department?->name ?? __('No department') }}</div>
            <div class="mt-2"><x-status-badge :status="$employee->status" /></div>
            @if($employee->isSeparated())
                <div class="small text-danger mt-2">{{ __(ucfirst($employee->status)) }} {{ $employee->status_changed_on?->format('d M Y') }}@if($employee->separation_reason) — {{ $employee->separation_reason }}@endif</div>
            @endif
        </div>

        <div class="dp purple mb-4">
            <div class="dp-head"><h2><i class="fa-solid fa-key me-1"></i> {{ __('Login account') }}</h2></div>
            <div class="dp-body small">
                @if($employee->user)
                    <div><strong>{{ $employee->user->email }}</strong></div>
                    <div class="text-body-secondary">{{ $employee->user->roleLabels() ?: '—' }}</div>
                    <div class="mt-1"><x-status-badge :status="$employee->user->status" /></div>
                @elseif($roles->isNotEmpty())
                    <form method="POST" action="{{ route('branch.hr.employees.account', $employee) }}" class="row g-2">
                        @csrf
                        <div class="col-12"><input type="email" name="account_email" class="form-control form-control-sm @error('account_email') is-invalid @enderror" placeholder="{{ __('Login e-mail') }}" value="{{ old('account_email', $employee->email) }}" required aria-label="{{ __('Login e-mail') }}">
                            @error('account_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><input type="password" name="account_password" class="form-control form-control-sm @error('account_password') is-invalid @enderror" placeholder="{{ __('Initial password') }}" autocomplete="new-password" required aria-label="{{ __('Initial password') }}">
                            @error('account_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><select name="account_role" class="form-select form-select-sm @error('account_role') is-invalid @enderror" required aria-label="{{ __('Role') }}">
                            <option value="">{{ __('Role') }}…</option>
                            @foreach($roles as $name => $label)<option value="{{ $name }}" @selected(old('account_role') === $name)>{{ $label }}</option>@endforeach
                        </select>@error('account_role')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12"><button class="btn btn-sm btn-brand w-100">{{ __('Create login') }}</button></div>
                    </form>
                @else
                    <span class="text-body-secondary">{{ __('No login account.') }}</span>
                @endif
            </div>
        </div>

        <div class="dp green">
            <div class="dp-head"><h2><i class="fa-solid fa-clipboard-check me-1"></i> {{ __('Attendance this month') }}</h2></div>
            <div class="dp-body">
                @php($pct = App\Enums\AttendanceStatus::percentage($attendance))
                <div class="d-flex flex-wrap gap-2">
                    @foreach(App\Enums\AttendanceStatus::cases() as $st)
                        <span class="badge text-bg-{{ $st->tone() }}">{{ $st->label() }}: {{ bn_digits($attendance[$st->value] ?? 0) }}</span>
                    @endforeach
                </div>
                <div class="small mt-2">{{ __('Attendance') }}: <strong>{{ $pct === null ? '—' : bn_digits($pct).'%' }}</strong></div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button" role="tab">{{ __('Profile') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-leave" type="button" role="tab">{{ __('Leave') }}</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">{{ __('Documents') }} <span class="badge text-bg-light border">{{ $employee->documents->count() }}</span></button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-history" type="button" role="tab">{{ __('Employment history') }}</button></li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab-profile" role="tabpanel">
                <div class="card card-body">
                    <div class="row g-3">
                        {!! $row(__('Type'), $employee->employee_type === 'teacher' ? __('Teacher') : __('Other staff')) !!}
                        {!! $row(__('Employment type'), __(ucfirst(str_replace('_', ' ', $employee->employment_type)))) !!}
                        {!! $row(__('Joining date'), $employee->joining_date?->format('d M Y')) !!}
                        {!! $row(__('Experience'), $employee->experience_years ? bn_digits($employee->experience_years).' '.__('years') : null) !!}
                        {!! $row(__('Gender'), __(ucfirst($employee->gender))) !!}
                        {!! $row(__('Date of birth'), $employee->date_of_birth?->format('d M Y')) !!}
                        {!! $row(__('Blood group'), $employee->blood_group) !!}
                        {!! $row(__('Religion'), $employee->religion) !!}
                        {!! $row(__('Mobile'), $employee->phone) !!}
                        {!! $row(__('E-mail'), $employee->email) !!}
                        {!! $row(__('Emergency contact'), trim($employee->emergency_contact_name.' '.$employee->emergency_contact_phone)) !!}
                        @if($canSeeConfidential)
                            {!! $row(__('NID number'), $employee->nid_number) !!}
                            {!! $row(__('Basic salary'), $employee->basic_salary !== null ? money_bdt($employee->basic_salary) : null) !!}
                        @endif
                        <div class="col-12"><div class="small text-body-secondary">{{ __('Qualification') }}</div><div style="white-space:pre-line">{{ $employee->qualification ?: '—' }}</div></div>
                        <div class="col-md-6"><div class="small text-body-secondary">{{ __('Present address') }}</div><div>{{ $employee->present_address ?: '—' }}</div></div>
                        <div class="col-md-6"><div class="small text-body-secondary">{{ __('Permanent address') }}</div><div>{{ $employee->permanent_address ?: '—' }}</div></div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-leave" role="tabpanel">
                <div class="card mb-3">
                    <div class="card-header bg-transparent"><h3 class="h6 mb-0">{{ __('Leave balance :year', ['year' => bn_digits(now()->year)]) }}</h3></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>{{ __('Leave type') }}</th><th>{{ __('Allowed') }}</th><th>{{ __('Taken') }}</th><th>{{ __('Pending') }}</th><th>{{ __('Remaining') }}</th></tr></thead>
                            <tbody>
                            @forelse($balances as $b)
                                <tr><td>{{ $b['type']->name }}</td><td>{{ $b['allowed'] === null ? __('Unlimited') : bn_digits($b['allowed']) }}</td>
                                    <td>{{ bn_digits($b['used']) }}</td><td>{{ bn_digits($b['pending']) }}</td><td class="fw-semibold">{{ $b['remaining'] === null ? '∞' : bn_digits($b['remaining']) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="text-body-secondary">{{ __('No leave types configured.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header bg-transparent d-flex"><h3 class="h6 mb-0 me-auto">{{ __('Recent applications') }}</h3>
                        @can('viewAll', App\Models\HR\LeaveApplication::class)<a href="{{ route('branch.leaves.index', ['employee_id' => $employee->id, 'status' => 'all']) }}" class="small">{{ __('View all') }}</a>@endcan</div>
                    <ul class="list-group list-group-flush">
                        @forelse($employee->leaveApplications as $la)
                            <li class="list-group-item d-flex gap-2">
                                <span class="me-auto">{{ $la->leaveType?->name }} · {{ $la->from_date->format('d M') }} – {{ $la->to_date->format('d M Y') }} ({{ bn_digits($la->days) }} {{ __('days') }})</span>
                                <span class="badge text-bg-{{ $la->tone() }}">{{ __(ucfirst($la->status)) }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No leave applications.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="tab-pane fade" id="tab-docs" role="tabpanel">
                <div class="card">
                    <ul class="list-group list-group-flush">
                        @forelse($employee->documents as $doc)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <i class="fa-regular fa-file-lines text-primary"></i>
                                <div class="me-auto"><div class="fw-medium">{{ $doc->title }}</div>
                                    <div class="small text-body-secondary">{{ __(ucfirst(str_replace('_', ' ', $doc->type))) }} · {{ number_format($doc->size / 1024, 0) }} KB · {{ $doc->uploader?->name }} · {{ $doc->created_at->format('d M Y') }}</div></div>
                                @if($doc->type !== 'nid' || $canSeeConfidential)
                                    <a href="{{ route('branch.hr.employees.documents.download', [$employee, $doc]) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Download') }}"><i class="bi bi-download"></i></a>
                                @endif
                                @if($canUpdate)
                                    <form method="POST" action="{{ route('branch.hr.employees.documents.destroy', [$employee, $doc]) }}" data-confirm="{{ __('Delete this document?') }}">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>
                                @endif
                            </li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No documents uploaded.') }}</li>
                        @endforelse
                    </ul>
                    @if($canUpdate)
                        <form method="POST" action="{{ route('branch.hr.employees.documents.store', $employee) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end">
                            @csrf
                            <div class="col-md-3"><label class="form-label small" for="doc_type">{{ __('Type') }}</label>
                                <select id="doc_type" name="type" class="form-select form-select-sm" required>
                                    @foreach($documentTypes as $t)<option value="{{ $t }}">{{ __(ucfirst(str_replace('_', ' ', $t))) }}</option>@endforeach
                                </select></div>
                            <div class="col-md-4"><label class="form-label small" for="doc_title">{{ __('Title') }}</label>
                                <input id="doc_title" name="title" class="form-control form-control-sm @error('title') is-invalid @enderror" required maxlength="191"></div>
                            <div class="col-md-3"><label class="form-label small" for="doc_file">{{ __('File (PDF/JPG/PNG, 5 MB)') }}</label>
                                <input id="doc_file" type="file" name="file" class="form-control form-control-sm @error('file') is-invalid @enderror" accept="application/pdf,image/jpeg,image/png,image/webp" required></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-brand w-100"><i class="bi bi-upload me-1"></i>{{ __('Upload') }}</button></div>
                            @error('file')<div class="col-12 small text-danger">{{ $message }}</div>@enderror
                        </form>
                    @endif
                </div>
            </div>

            <div class="tab-pane fade" id="tab-history" role="tabpanel">
                <div class="card">
                    <ul class="list-group list-group-flush">
                        @forelse($employee->histories as $h)
                            <li class="list-group-item">
                                <div class="d-flex"><strong class="me-auto">{{ $h->label() }}</strong><span class="small text-body-secondary">{{ $h->effective_on->format('d M Y') }}</span></div>
                                @if($h->from_value || $h->to_value)<div class="small">{{ $h->from_value ?: '—' }} → {{ $h->to_value ?: '—' }}</div>@endif
                                @if($h->note)<div class="small text-body-secondary">{{ $h->note }}</div>@endif
                                <div class="small text-body-secondary">{{ __('Recorded by') }}: {{ $h->recorder?->name ?? __('System') }}</div>
                            </li>
                        @empty
                            <li class="list-group-item text-body-secondary">{{ __('No history yet.') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@can('changeStatus', $employee)
<div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('branch.hr.employees.status', $employee) }}" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header"><h2 class="modal-title h6" id="statusModalLabel">{{ __('Change employment status') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label class="form-label" for="st_status">{{ __('New status') }}</label>
                    <select id="st_status" name="status" class="form-select" required>
                        @foreach(array_diff(App\Models\HR\Employee::STATUSES, [$employee->status]) as $st)
                            <option value="{{ $st }}">{{ $st === 'active' ? __('Reinstate (active)') : __(ucfirst($st)) }}</option>
                        @endforeach
                    </select></div>
                <div class="col-md-6"><label class="form-label" for="st_date">{{ __('Effective date') }}</label>
                    <input id="st_date" type="date" name="date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                <div class="col-12"><label class="form-label" for="st_reason">{{ __('Reason') }}</label>
                    <textarea id="st_reason" name="reason" rows="2" class="form-control" maxlength="500" placeholder="{{ __('Required for resignation, termination and retirement') }}"></textarea></div>
                <div class="col-12 small text-body-secondary">{{ __('Separated or suspended employees lose their login immediately. The record and history are kept.') }}</div>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">{{ __('Confirm') }}</button></div>
        </form>
    </div>
</div>
@endcan
@include('branch.hr.certificates._modal')
@endsection
