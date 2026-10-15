@extends('layouts.branch')
@section('title', $campaign->exists ? __('Edit admission') : __('New admission'))

@php
    $fieldLabels = [
    'name_bn' => __('Name (Bangla)'), 'birth_registration_no' => __('Birth registration no.'), 'religion' => __('Religion'), 'blood_group' => __('Blood group'),
    'email' => __('E-mail'), 'permanent_address' => __('Permanent address'), 'previous_school' => __('Previous school'), 'previous_class' => __('Previous class'),
    'previous_result' => __('Previous result'), 'father_name' => __('Father’s name'), 'mother_name' => __('Mother’s name'), 'guardian_occupation' => __('Guardian occupation'),
    'emergency_contact_phone' => __('Emergency contact mobile'), 'photo' => __('Photo'),
    ];
@endphp

@section('content')
<x-page-header :title="$campaign->exists ? __('Edit admission') : __('New admission')"
    :breadcrumbs="[__('Admissions') => route('branch.admissions.campaigns.index'), ($campaign->exists ? $campaign->title : __('New')) => null]" />

<form method="POST" action="{{ $campaign->exists ? route('branch.admissions.campaigns.update', $campaign) : route('branch.admissions.campaigns.store') }}"
      x-data="{ test: {{ old('has_test', $campaign->has_test) ? 'true' : 'false' }} }">
    @csrf
    @if($campaign->exists) @method('PUT') @endif
    <div class="card mb-3">
        <div class="card-body row g-3">
            <x-form.input name="title" :label="__('Title')" :value="$campaign->title" required col="col-md-6" maxlength="191" placeholder="{{ __('e.g. Class Six admission 2027') }}" />
            <x-form.select name="academic_session_id" :label="__('Session')" :value="$campaign->academic_session_id" required col="col-md-3" :options="$sessions->all()" placeholder="—" />
            <x-form.select name="school_class_id" :label="__('Class')" :value="$campaign->school_class_id" required col="col-md-3" :options="$classes->all()" placeholder="—" />
            <x-form.input name="starts_on" type="date" :label="__('Applications from')" :value="$campaign->starts_on?->toDateString()" required col="col-md-3" />
            <x-form.input name="ends_on" type="date" :label="__('Last date')" :value="$campaign->ends_on?->toDateString()" required col="col-md-3" />
            <x-form.input name="seats" type="number" min="1" :label="__('Seats')" :value="$campaign->seats" required col="col-md-2" />
            <x-form.input name="min_age" type="number" min="2" max="40" :label="__('Min age')" :value="$campaign->min_age" col="col-md-2" />
            <x-form.input name="max_age" type="number" min="2" max="60" :label="__('Max age')" :value="$campaign->max_age" col="col-md-2" :help="__('On session start')" />
            <x-form.input name="admission_fee" type="number" step="0.01" min="0" :label="__('Admission fee (BDT)')" :value="$campaign->admission_fee" required col="col-md-3" />
            <x-form.switch name="fee_before_admission" :label="__('Fee must be paid before admission')" :checked="$campaign->fee_before_admission" col="col-md-4 pt-md-4" />
            <x-form.switch name="is_online" :label="__('Accept online applications')" :checked="$campaign->is_online" col="col-md-4 pt-md-4" />
            <x-form.select name="status" :label="__('Status')" :value="$campaign->status" required col="col-md-3"
                :options="['draft' => __('Draft'), 'open' => __('Open'), 'closed' => __('Closed')]" />
            <div class="col-12"><label class="form-label" for="instructions">{{ __('Instructions shown to applicants') }}</label>
                <textarea id="instructions" name="instructions" rows="3" class="form-control" maxlength="5000">{{ old('instructions', $campaign->instructions) }}</textarea></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-transparent d-flex align-items-center">
            <h2 class="h6 mb-0 me-auto">{{ __('Admission test') }}</h2>
            <div class="form-check form-switch mb-0"><input type="hidden" name="has_test" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="has_test" name="has_test" value="1" x-model="test">
                <label class="form-check-label" for="has_test">{{ __('Admission test is held') }}</label></div>
        </div>
        <div class="card-body row g-3" x-show="test" x-cloak>
            <x-form.input name="test_at" type="datetime-local" :label="__('Date & time')" :value="$campaign->test_at?->format('Y-m-d\TH:i')" col="col-md-4" />
            <x-form.input name="test_venue" :label="__('Venue')" :value="$campaign->test_venue" col="col-md-4" maxlength="191" />
            <x-form.input name="test_total_marks" type="number" step="0.01" :label="__('Total marks')" :value="$campaign->test_total_marks" col="col-md-2" />
            <x-form.input name="test_pass_marks" type="number" step="0.01" :label="__('Pass marks')" :value="$campaign->test_pass_marks" col="col-md-2" />
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Required documents') }}</h2></div>
                <div class="card-body">
                    @foreach(App\Models\Admissions\AdmissionCampaign::DOCUMENT_TYPES as $type)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="required_documents[]" value="{{ $type }}" id="rd_{{ $type }}"
                            @checked(in_array($type, old('required_documents', $campaign->required_documents ?? []), true))>
                            <label class="form-check-label" for="rd_{{ $type }}">{{ __(ucfirst(str_replace('_', ' ', $type))) }}</label></div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-transparent"><h2 class="h6 mb-0">{{ __('Application form fields') }}</h2><div class="small text-body-secondary">{{ __('Name, gender, date of birth, mobile, address and guardian are always required.') }}</div></div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Field') }}</th><th class="text-center">{{ __('Hidden') }}</th><th class="text-center">{{ __('Optional') }}</th><th class="text-center">{{ __('Required') }}</th></tr></thead>
                    <tbody>
                    @foreach(App\Models\Admissions\AdmissionCampaign::OPTIONAL_FIELDS as $field)
                        @php($mode = old('form_settings.'.$field, $campaign->fieldMode($field)))
                        <tr><td>{{ $fieldLabels[$field] ?? $field }}</td>
                            @foreach(['hidden', 'optional', 'required'] as $m)
                                <td class="text-center"><input class="form-check-input" type="radio" name="form_settings[{{ $field }}]" value="{{ $m }}" @checked($mode === $m) aria-label="{{ $fieldLabels[$field] ?? $field }}: {{ $m }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-brand">{{ __('Save') }}</button>
        <a href="{{ route('branch.admissions.campaigns.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
