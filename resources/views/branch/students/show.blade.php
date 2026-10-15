@extends('layouts.branch')
@section('title', $student->name)

@php($current = $student->enrollments->firstWhere('status', 'enrolled'))

@section('content')
<x-page-header :title="$student->name" :breadcrumbs="[__('Students') => route('branch.students.index'), $student->student_code => null]">
    <x-slot:actions>
        @can('update', $student)
            <a href="{{ route('branch.students.edit', $student) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit profile') }}</a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">{{ __('Change status') }}</button>
                <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 260px">
                    <form method="POST" action="{{ route('branch.students.status', $student) }}" data-confirm="{{ __('Change this student\'s status?') }}">
                        @csrf @method('PATCH')
                        <select name="status" class="form-select form-select-sm mb-2" aria-label="{{ __('Status') }}">
                            @foreach(App\Models\Students\Student::STATUSES as $st)<option value="{{ $st }}" @selected($student->status === $st)>{{ __(ucfirst($st)) }}</option>@endforeach
                        </select>
                        <input type="date" name="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm mb-2" aria-label="{{ __('Effective date') }}">
                        <button class="btn btn-sm btn-brand w-100">{{ __('Apply') }}</button>
                    </form>
                </div>
            </div>
        @endcan
        @if($student->isActive())@can('id-cards.issue')
            <a href="{{ route('branch.idcards.student', $student) }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener"><i class="fa-solid fa-id-badge me-1"></i>{{ __('ID card') }}</a>
        @endcan @endif
        @can('certificates.issue')
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#certModal"><i class="fa-solid fa-award me-1"></i>{{ __('Certificate') }}</button>
        @endcan
        @if($student->isActive() && $otherBranches->isNotEmpty())
            @can('students.transfer')
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#transferModal"><i class="fa-solid fa-right-left me-1"></i>{{ __('Transfer') }}</button>
            @endcan
        @endif
        @if(! $student->isActive() && $student->status !== 'graduated')
            @can('changeStatus', $student)
                <button type="button" class="btn btn-brand btn-sm" data-bs-toggle="modal" data-bs-target="#readmitModal"><i class="fa-solid fa-rotate-left me-1"></i>{{ __('Re-admit') }}</button>
            @endcan
        @endif
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                @if($student->photo_path)
                    <img src="{{ route('branch.students.photo', $student) }}" alt="" class="rounded mb-2" width="120" height="140" style="object-fit: cover">
                @else
                    <span class="avatar mb-2" style="width:96px;height:96px;font-size:2rem">{{ $student->initials() }}</span>
                @endif
                <h2 class="h5 mb-0">{{ $student->name }}</h2>
                <div class="bn text-body-secondary">{{ $student->name_bn }}</div>
                <div class="mt-2"><code>{{ $student->student_code }}</code> <x-status-badge :status="$student->status" /></div>
                @if($current)
                    <div class="small mt-2">{{ $current->schoolClass?->name }} · {{ __('Section') }} {{ $current->section?->name }} · {{ __('Roll') }} {{ bn_digits($current->roll_no) }}
                        @if($current->studyGroup)<br>{{ $current->studyGroup->name }}@endif</div>
                @endif
            </div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex"><span class="text-body-secondary">{{ __('Date of birth') }}</span><span class="ms-auto">{{ $student->date_of_birth?->format('d M Y') }} ({{ $student->ageOn() }})</span></li>
                <li class="list-group-item d-flex"><span class="text-body-secondary">{{ __('Gender') }}</span><span class="ms-auto">{{ __(ucfirst($student->gender)) }}</span></li>
                <li class="list-group-item d-flex"><span class="text-body-secondary">{{ __('Blood group') }}</span><span class="ms-auto">{{ $student->blood_group ?? '—' }}</span></li>
                <li class="list-group-item d-flex"><span class="text-body-secondary">{{ __('Birth reg.') }}</span><span class="ms-auto">{{ $student->birth_registration_no ?? '—' }}</span></li>
                <li class="list-group-item d-flex"><span class="text-body-secondary">{{ __('Religion') }}</span><span class="ms-auto">{{ $student->religion ?? '—' }}</span></li>
                <li class="list-group-item d-flex"><span class="text-body-secondary">{{ __('Admitted') }}</span><span class="ms-auto">{{ $student->admission_date?->format('d M Y') }}</span></li>
                <li class="list-group-item d-flex"><span class="text-body-secondary">{{ __('Mobile') }}</span><span class="ms-auto">{{ $student->phone ?? '—' }}</span></li>
                <li class="list-group-item"><span class="text-body-secondary">{{ __('Present address') }}</span><div>{{ $student->present_address ?? '—' }}</div></li>
                <li class="list-group-item"><span class="text-body-secondary">{{ __('Emergency contact') }}</span><div>{{ $student->emergency_contact_name ?? '—' }} {{ $student->emergency_contact_phone }}</div></li>
                @if($canSeeHealth)
                    <li class="list-group-item bg-warning-subtle"><span class="text-body-secondary">{{ __('Medical notes') }}</span><div>{{ $student->medical_notes ?: '—' }}</div></li>
                @endif
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Parents / guardians') }}</h2></div>
            <ul class="list-group list-group-flush">
                @forelse($student->guardians as $g)
                    <li class="list-group-item d-flex align-items-center">
                        <div>
                            <div class="fw-semibold">{{ $g->name }} <span class="badge text-bg-light border">{{ __(ucfirst($g->pivot->relation)) }}</span>
                                @if($g->pivot->is_primary)<span class="badge text-bg-success">{{ __('Primary') }}</span>@endif</div>
                            <div class="small text-body-secondary">{{ $g->phone }}{{ $g->occupation ? ' · '.$g->occupation : '' }}{{ $g->email ? ' · '.$g->email : '' }}</div>
                        </div>
                        @can('update', $student)
                            <form method="POST" action="{{ route('branch.students.guardians.destroy', [$student, $g]) }}" class="ms-auto" data-confirm="{{ __('Unlink this guardian?') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0">{{ __('Unlink') }}</button>
                            </form>
                        @endcan
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary small">{{ __('No guardian linked.') }}</li>
                @endforelse
            </ul>
            @can('update', $student)
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('branch.students.guardians.store', $student) }}" class="row g-2 align-items-end">
                        @csrf
                        <x-form.input name="name" :label="__('Name')" col="col-md-3" required />
                        <x-form.input name="phone" :label="__('Mobile')" col="col-md-3" required />
                        <x-form.select name="relation" :label="__('Relation')" col="col-md-2" :options="collect($relations)->mapWithKeys(fn ($r) => [$r => __(ucfirst($r))])" />
                        <x-form.input name="occupation" :label="__('Occupation')" col="col-md-2" />
                        <div class="col-md-2"><button class="btn btn-outline-secondary w-100">{{ __('Link guardian') }}</button></div>
                    </form>
                </div>
            @endcan
        </div>

        @can('update', $student)
            @include('branch.students.partials.portal-logins')
        @endcan

        <div class="card mb-3">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Academic history') }}</h2></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Session') }}</th><th>{{ __('Class') }}</th><th>{{ __('Section') }}</th><th>{{ __('Group') }}</th><th>{{ __('Roll') }}</th><th>{{ __('Outcome') }}</th><th>{{ __('Period') }}</th></tr></thead>
                    <tbody>
                    @forelse($student->enrollments as $e)
                        <tr>
                            <td>{{ $e->session?->name }}</td><td>{{ $e->schoolClass?->name }}</td><td>{{ $e->section?->name }}</td>
                            <td>{{ $e->studyGroup?->name ?? '—' }}</td><td>{{ bn_digits($e->roll_no) }}</td>
                            <td><span class="badge text-bg-{{ $e->status === 'enrolled' ? 'success' : 'light border' }}">{{ __(ucfirst($e->status)) }}</span></td>
                            <td class="small">{{ $e->enrolled_on?->format('d M Y') }} – {{ $e->left_on?->format('d M Y') ?? __('now') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-body-secondary small">{{ __('Not enrolled in any session.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Documents') }}</h2></div>
            <ul class="list-group list-group-flush">
                @forelse($student->documents as $doc)
                    <li class="list-group-item d-flex align-items-center small">
                        <i class="bi {{ str_contains($doc->mime_type, 'pdf') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image' }} me-2 fs-5"></i>
                        <div>
                            <a href="{{ route('branch.students.documents.download', [$student, $doc]) }}" class="fw-semibold">{{ $doc->title }}</a>
                            <div class="text-body-secondary">{{ __(ucwords(str_replace('_', ' ', $doc->type))) }} · {{ number_format($doc->size / 1024) }} KB · {{ $doc->uploader?->name }} · {{ $doc->created_at?->format('d M Y') }}</div>
                        </div>
                        @can('update', $student)
                            <form method="POST" action="{{ route('branch.students.documents.destroy', [$student, $doc]) }}" class="ms-auto" data-confirm="{{ __('Delete this document?') }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                            </form>
                        @endcan
                    </li>
                @empty
                    <li class="list-group-item text-body-secondary small">{{ __('No documents uploaded.') }}</li>
                @endforelse
            </ul>
            @can('update', $student)
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('branch.students.documents.store', $student) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <x-form.select name="type" :label="__('Type')" col="col-md-3" :options="collect($documentTypes)->mapWithKeys(fn ($t) => [$t => __(ucwords(str_replace('_', ' ', $t)))])" />
                        <x-form.input name="title" :label="__('Title')" col="col-md-3" required />
                        <div class="col-md-4">
                            <label class="form-label" for="file">{{ __('File (PDF/JPG/PNG, ≤5 MB)') }}</label>
                            <input id="file" type="file" name="file" required accept="application/pdf,image/jpeg,image/png,image/webp" class="form-control @error('file') is-invalid @enderror">
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2"><button class="btn btn-outline-secondary w-100">{{ __('Upload') }}</button></div>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</div>
@include('branch.students.partials.lifecycle-modals')
@endsection
