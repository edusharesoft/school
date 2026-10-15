@extends('layouts.branch')
@section('title', $application->application_no)

@php
    $campaign = $application->campaign;
    $u = auth()->user();
    $row = fn ($label, $value) => '<div class="col-sm-6 col-lg-4"><div class="small text-body-secondary">'.e($label).'</div><div class="fw-medium">'.($value !== null && $value !== '' ? e($value) : '—').'</div></div>';
@endphp

@section('content')
<x-page-header :title="$application->name" :breadcrumbs="[__('Applications') => route('branch.admissions.applications.index', ['campaign_id' => $campaign->id]), $application->application_no => null]" />

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-transparent d-flex align-items-center gap-2">
                <code class="me-auto">{{ $application->application_no }}</code>
                <span class="badge text-bg-light border">{{ __(ucfirst($application->source)) }}</span>
                <span class="badge text-bg-{{ $application->tone() }} fs-6">{{ __(ucfirst($application->status)) }}</span>
            </div>
            <div class="card-body row g-3">
                {!! $row(__('Admission'), $campaign->title.' — '.$campaign->schoolClass?->name.' ('.$campaign->session?->name.')') !!}
                {!! $row(__('Name (Bangla)'), $application->name_bn) !!}
                {!! $row(__('Gender'), __(ucfirst($application->gender))) !!}
                {!! $row(__('Date of birth'), $application->date_of_birth->format('d M Y')) !!}
                {!! $row(__('Birth registration no.'), $application->birth_registration_no) !!}
                {!! $row(__('Religion'), $application->religion) !!}
                {!! $row(__('Blood group'), $application->blood_group) !!}
                {!! $row(__('Contact mobile'), $application->phone) !!}
                {!! $row(__('E-mail'), $application->email) !!}
                {!! $row(__('Previous school'), trim($application->previous_school.' '.$application->previous_class.' '.$application->previous_result)) !!}
                {!! $row(__('Father’s name'), $application->father_name) !!}
                {!! $row(__('Mother’s name'), $application->mother_name) !!}
                {!! $row(__('Guardian'), $application->guardian_name.' ('.__(ucfirst($application->guardian_relation)).')') !!}
                {!! $row(__('Guardian mobile'), $application->guardian_phone) !!}
                {!! $row(__('Occupation'), $application->guardian_occupation) !!}
                <div class="col-md-6"><div class="small text-body-secondary">{{ __('Present address') }}</div><div>{{ $application->present_address }}</div></div>
                <div class="col-md-6"><div class="small text-body-secondary">{{ __('Permanent address') }}</div><div>{{ $application->permanent_address ?: '—' }}</div></div>
                @if($campaign->has_test)
                    {!! $row(__('Test marks'), $application->test_absent ? __('Absent') : ($application->test_marks === null ? null : $application->test_marks.' / '.$campaign->test_total_marks)) !!}
                @endif
                {!! $row(__('Merit position'), $application->merit_position) !!}
                {!! $row(__('Waiting list position'), $application->waiting_position) !!}
                @if($application->remarks)<div class="col-12"><div class="small text-body-secondary">{{ __('Remarks') }}</div><div>{{ $application->remarks }}</div></div>@endif
                <div class="col-12 small text-body-secondary">{{ __('Submitted') }}: {{ $application->created_at->format('d M Y, h:i A') }}@if($application->creator) · {{ $application->creator->name }}@endif
                    @if($application->decider) · {{ __('Last decision') }}: {{ $application->decider->name }}, {{ $application->decided_at?->format('d M Y') }}@endif</div>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Documents') }}</h2></div>
            <ul class="list-group list-group-flush">
                @forelse($application->documents as $d)
                    <li class="list-group-item d-flex"><span class="me-auto"><i class="fa-regular fa-file-lines me-1"></i>{{ $d->title }} <span class="small text-body-secondary">({{ number_format($d->size / 1024) }} KB)</span></span>
                        <a href="{{ route('branch.admissions.applications.documents.download', [$application, $d]) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Download') }}"><i class="bi bi-download"></i></a></li>
                @empty
                    <li class="list-group-item text-body-secondary">{{ __('No documents uploaded.') }}</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        @if($application->photo_path)
            <div class="panel panel-body text-center mb-3"><img src="{{ route('branch.admissions.applications.photo', $application) }}" alt="" class="rounded" style="max-width:160px"></div>
        @endif

        @if($application->student)
            <div class="alert alert-success"><i class="fa-solid fa-user-check me-1"></i> {{ __('Admitted as') }}
                <a href="{{ route('branch.students.show', $application->student) }}"><strong>{{ $application->student->student_code }}</strong></a></div>
        @endif

        @if((float) $application->fee_amount > 0)
            <div class="dp green mb-3">
                <div class="dp-head"><h2><i class="fa-solid fa-money-bill me-1"></i> {{ __('Admission fee') }}</h2><span class="sub">{{ money_bdt($application->fee_amount) }}</span></div>
                <div class="dp-body small">
                    <div class="mb-2">{{ __('Status') }}: <span class="badge text-bg-{{ $application->feeSettled() ? 'success' : 'warning' }}">{{ __(ucfirst($application->fee_status)) }}</span>
                        @if($application->fee_reference) · {{ $application->fee_reference }}@endif</div>
                    @can('admissions.collect-fee')
                        @unless($application->isFinal() && $application->status !== 'admitted')
                            <form method="POST" action="{{ route('branch.admissions.applications.fee', $application) }}" class="row g-2">@csrf
                                <div class="col-5"><select name="fee_status" class="form-select form-select-sm" aria-label="{{ __('Fee status') }}">
                                    @foreach(['paid' => __('Paid'), 'waived' => __('Waived'), 'unpaid' => __('Unpaid')] as $k => $v)<option value="{{ $k }}" @selected($application->fee_status === $k)>{{ $v }}</option>@endforeach
                                </select></div>
                                <div class="col-7"><input name="fee_reference" class="form-control form-control-sm" placeholder="{{ __('Receipt / transaction no.') }}" value="{{ $application->fee_reference }}" maxlength="60" aria-label="{{ __('Reference') }}"></div>
                                <div class="col-12"><button class="btn btn-sm btn-outline-success w-100">{{ __('Record') }}</button></div>
                            </form>
                            <div class="text-body-secondary mt-2">{{ __('Invoices and receipts are generated by the fee module (Part 4); record the manual receipt number here until then.') }}</div>
                        @endunless
                    @endcan
                </div>
            </div>
        @endif

        @if($application->status === 'selected' && $u->can('admissions.approve') && $u->can('students.create'))
            <div class="dp blue mb-3">
                <div class="dp-head"><h2><i class="fa-solid fa-user-plus me-1"></i> {{ __('Admit') }}</h2></div>
                <form method="POST" action="{{ route('branch.admissions.applications.admit', $application) }}" class="dp-body row g-2">@csrf
                    <div class="col-6"><label class="form-label small" for="section_id">{{ __('Section') }}</label>
                        <select id="section_id" name="section_id" class="form-select form-select-sm" required>
                            @foreach($sections as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ bn_digits($s->activeCount($campaign->academic_session_id)) }}/{{ bn_digits($s->capacity) }})</option>@endforeach
                        </select></div>
                    <div class="col-6"><label class="form-label small" for="roll_no">{{ __('Roll (optional)') }}</label>
                        <input id="roll_no" type="number" min="1" name="roll_no" class="form-control form-control-sm"></div>
                    @if($groups->isNotEmpty())
                        <div class="col-12"><label class="form-label small" for="study_group_id">{{ __('Group') }}</label>
                            <select id="study_group_id" name="study_group_id" class="form-select form-select-sm" required>
                                @foreach($groups as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select></div>
                    @endif
                    <div class="col-12"><button class="btn btn-brand btn-sm w-100" @disabled($campaign->fee_before_admission && ! $application->feeSettled())>{{ __('Create student & enroll') }}</button>
                        @if($campaign->fee_before_admission && ! $application->feeSettled())<div class="small text-danger mt-1">{{ __('The admission fee must be paid or waived before admission.') }}</div>@endif</div>
                </form>
            </div>
        @endif

        @unless($application->isFinal())
            <div class="dp orange">
                <div class="dp-head"><h2><i class="fa-solid fa-gavel me-1"></i> {{ __('Decision') }}</h2></div>
                <div class="dp-body d-grid gap-2">
                    @can('admissions.update')
                        @if($application->status === 'submitted')
                            <form method="POST" action="{{ route('branch.admissions.applications.status', $application) }}">@csrf @method('PATCH')
                                <input type="hidden" name="status" value="verified"><button class="btn btn-sm btn-outline-info w-100">{{ __('Mark documents verified') }}</button></form>
                        @endif
                    @endcan
                    @can('admissions.approve')
                        <form method="POST" action="{{ route('branch.admissions.applications.status', $application) }}" class="d-flex gap-2">@csrf @method('PATCH')
                            <input type="hidden" name="status" value="rejected">
                            <input name="remarks" class="form-control form-control-sm" placeholder="{{ __('Reason') }}" required maxlength="500" aria-label="{{ __('Reason') }}">
                            <button class="btn btn-sm btn-outline-danger">{{ __('Reject') }}</button></form>
                        <form method="POST" action="{{ route('branch.admissions.applications.status', $application) }}" data-confirm="{{ __('Cancel this application?') }}">@csrf @method('PATCH')
                            <input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-outline-secondary w-100">{{ __('Cancel application') }}</button></form>
                    @endcan
                </div>
            </div>
        @endunless
    </div>
</div>
@endsection
