@extends('layouts.branch')
@section('title', __('Send message'))

@php
    $labels = ['in_app' => __('In-app (portal)'), 'sms' => __('SMS'), 'email' => __('E-mail'), 'whatsapp' => __('WhatsApp')];
    $chosen = old('channels', ['in_app']);
@endphp

@section('content')
<x-page-header :title="__('Send message')" :breadcrumbs="[__('Communication') => null, __('Send message') => null]" />

@if(count($channels) === 1)
    <div class="alert alert-info small">{{ __('Only in-app messages are switched on. SMS, e-mail and WhatsApp are enabled in the SMS, e-mail & WhatsApp settings.') }}
        @can('notifications.configure')<a href="{{ route('branch.communication.settings') }}">{{ __('Open settings') }}</a>@endcan</div>
@endif

<form method="POST" action="{{ route('branch.communication.messages.store') }}" class="card" style="max-width: 980px" data-confirm="{{ __('Send this message to everyone in the audience?') }}">
    @csrf
    <div class="card-body row g-3">
        <x-form.input name="title" :label="__('Title (for the history and the e-mail / in-app subject)')" required col="col-md-8" maxlength="150" />
        <div class="col-md-4">
            <label class="form-label" for="audience">{{ __('Audience') }}</label>
            <select id="audience" name="audience" class="form-select @error('audience') is-invalid @enderror" data-audience>
                @foreach(App\Models\Communication\Notice::AUDIENCES as $a)<option value="{{ $a }}" @selected(old('audience', 'guardians') === $a)>{{ __(Illuminate\Support\Str::headline($a)) }}</option>@endforeach
            </select>
            @error('audience')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 row g-2" data-audience-for="classes">
            <div class="col-md-6">
                <label class="form-label" for="class_ids">{{ __('Classes') }}</label>
                <select id="class_ids" name="class_ids[]" multiple size="5" class="form-select @error('class_ids') is-invalid @enderror">
                    @foreach($classes as $id => $name)<option value="{{ $id }}" @selected(in_array((string) $id, (array) old('class_ids', []), true))>{{ $name }}</option>@endforeach
                </select>
                @error('class_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="section_ids">{{ __('or sections') }}</label>
                <select id="section_ids" name="section_ids[]" multiple size="5" class="form-select">
                    @foreach($sections as $id => $name)<option value="{{ $id }}" @selected(in_array((string) $id, (array) old('section_ids', []), true))>{{ $name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="col-12" data-audience-for="students_specific">
            <label class="form-label" for="student_search">{{ __('Students (their guardians are included)') }}</label>
            <div data-student-picker data-url="{{ route('branch.communication.students.search') }}" data-name="student_ids[]">
                <div data-chosen class="mb-1"></div>
                <input id="student_search" type="search" class="form-control @error('student_ids') is-invalid @enderror" placeholder="{{ __('Type a name or student ID') }}" autocomplete="off">
                @error('student_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="list-group mt-1" data-results></div>
            </div>
        </div>

        <div class="col-12">
            <div class="form-label mb-1">{{ __('Channels') }}</div>
            @foreach($channels as $ch)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $ch }}" id="c_{{ $ch }}" @checked(in_array($ch, $chosen, true))>
                    <label class="form-check-label" for="c_{{ $ch }}">{{ $labels[$ch] }}</label>
                </div>
            @endforeach
            @error('channels')<div class="text-danger small">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('WhatsApp only sends when an approved template is set for "Message from the school". Each person gets the message in their language; the other text is used when one is empty.') }}</div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="body_bn">{{ __('Message in Bangla') }}</label>
            <textarea id="body_bn" name="body_bn" rows="5" maxlength="1000" class="form-control @error('body_bn') is-invalid @enderror" data-sms-counter="cnt_bn">{{ old('body_bn') }}</textarea>
            <div class="form-text" id="cnt_bn" data-chars="{{ __('characters') }}" data-parts="{{ __('SMS part(s)') }}"></div>
            @error('body_bn')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="body_en">{{ __('Message in English') }}</label>
            <textarea id="body_en" name="body_en" rows="5" maxlength="1000" class="form-control" data-sms-counter="cnt_en">{{ old('body_en') }}</textarea>
            <div class="form-text" id="cnt_en" data-chars="{{ __('characters') }}" data-parts="{{ __('SMS part(s)') }}"></div>
        </div>
        <div class="col-12 small text-body-secondary">{{ __('SMS adds " - :branch" at the end. Bangla SMS: 70 characters in one part, 67 per part when longer; English: 160 / 153.', ['branch' => tenant()->branch()->displayName()]) }}</div>
        <x-form.input name="scheduled_at" type="datetime-local" :label="__('Send later (optional)')" col="col-md-4" />
        <x-form.switch name="is_emergency" :label="__('Emergency (also reaches people who opted out)')" col="col-md-8 d-flex align-items-end" />
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        <button class="btn btn-brand" type="submit"><i class="bi bi-send me-1"></i>{{ __('Send') }}</button>
        <a href="{{ route('branch.communication.messages.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
