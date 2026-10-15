@extends('layouts.branch')
@section('title', $notice->exists ? __('Edit notice') : __('New notice'))

@php
    $filter = (array) $notice->audience_filter;
    $chosenClasses = array_map('intval', old('class_ids', $filter['class_ids'] ?? []));
    $chosenSections = array_map('intval', old('section_ids', $filter['section_ids'] ?? []));
    $chosenChannels = old('channels', $notice->channels ?? []);
    $canSend = auth()->user()->can('notifications.send');
    $enabled = app(App\Services\Notifications\NotificationSettings::class)->enabledChannels();
@endphp

@section('content')
<x-page-header :title="$notice->exists ? __('Edit notice') : __('New notice')" :breadcrumbs="[__('Notices') => route('branch.communication.notices.index'), ($notice->exists ? __('Edit') : __('New')) => null]" />

<form method="POST" enctype="multipart/form-data" class="card" style="max-width: 980px"
      action="{{ $notice->exists ? route('branch.communication.notices.update', $notice) : route('branch.communication.notices.store') }}">
    @csrf @if($notice->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <x-form.input name="title" :label="__('Title')" :value="$notice->title" required col="col-md-8" maxlength="191" />
        <x-form.select name="category" :label="__('Category')" :value="$notice->category" required col="col-md-4"
            :options="collect(App\Models\Communication\Notice::CATEGORIES)->mapWithKeys(fn ($c) => [$c => __(ucfirst($c))])->all()" />
        <div class="col-12">
            <label class="form-label" for="body">{{ __('Notice text') }} <span class="text-danger">*</span></label>
            <textarea id="body" name="body" rows="9" maxlength="10000" required class="form-control @error('body') is-invalid @enderror">{{ old('body', $notice->body) }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('Who sees it') }}</div>
        <div class="col-md-4">
            <label class="form-label" for="audience">{{ __('Audience') }}</label>
            <select id="audience" name="audience" class="form-select" data-audience>
                @foreach(App\Models\Communication\Notice::AUDIENCES as $a)
                    <option value="{{ $a }}" @selected(old('audience', $notice->audience) === $a)>{{ __(Illuminate\Support\Str::headline($a)) }}</option>
                @endforeach
            </select>
            <div class="form-text">{{ __('"Classes" and "Students specific" reach those students and their guardians.') }}</div>
        </div>
        <div class="col-md-8 row g-2" data-audience-for="classes">
            <div class="col-md-6">
                <label class="form-label" for="class_ids">{{ __('Classes') }}</label>
                <select id="class_ids" name="class_ids[]" multiple size="6" class="form-select @error('class_ids') is-invalid @enderror">
                    @foreach($classes as $id => $name)<option value="{{ $id }}" @selected(in_array($id, $chosenClasses, true))>{{ $name }}</option>@endforeach
                </select>
                @error('class_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="section_ids">{{ __('or sections') }}</label>
                <select id="section_ids" name="section_ids[]" multiple size="6" class="form-select">
                    @foreach($sections as $id => $name)<option value="{{ $id }}" @selected(in_array($id, $chosenSections, true))>{{ $name }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="col-md-8" data-audience-for="students_specific">
            <label class="form-label" for="student_search">{{ __('Students') }}</label>
            <div data-student-picker data-url="{{ route('branch.communication.students.search') }}" data-name="student_ids[]">
                <div data-chosen class="mb-1">
                    @foreach($selectedStudents as $s)
                        <span class="badge text-bg-light border me-1 mb-1">{{ $s->name }} ({{ $s->student_code }})<input type="hidden" name="student_ids[]" value="{{ $s->id }}">
                            <button type="button" class="btn-close ms-1" style="font-size:.6rem" data-remove aria-label="{{ __('Remove') }}"></button></span>
                    @endforeach
                </div>
                <input id="student_search" type="search" class="form-control @error('student_ids') is-invalid @enderror" placeholder="{{ __('Type a name or student ID') }}" autocomplete="off">
                @error('student_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="list-group mt-1" data-results></div>
            </div>
        </div>

        <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('When') }}</div>
        <x-form.input name="publish_at" type="datetime-local" :label="__('Publish at (empty = when published)')" :value="$notice->publish_at?->format('Y-m-d\TH:i')" col="col-md-4" />
        <x-form.input name="expires_at" type="datetime-local" :label="__('Expires at (optional)')" :value="$notice->expires_at?->format('Y-m-d\TH:i')" col="col-md-4" />
        <div class="col-md-4">
            <label class="form-label" for="attachment">{{ __('Attachment (PDF, image, Word, Excel — max 10 MB)') }}</label>
            <input id="attachment" name="attachment" type="file" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
            @if($notice->attachment_name)<div class="form-text">{{ __('Current') }}: {{ $notice->attachment_name }}</div>@endif
            @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12 small fw-semibold text-uppercase text-body-secondary mt-3">{{ __('Options') }}</div>
        <x-form.switch name="is_emergency" :label="__('Emergency notice (shown first; SMS / e-mail also reach people who opted out)')" :checked="$notice->is_emergency" col="col-md-6" />
        <x-form.switch name="is_pinned" :label="__('Pin to the top of the board')" :checked="$notice->is_pinned" col="col-md-6" />
        <x-form.switch name="is_public" :label="__('Also show on the public website')" :checked="$notice->is_public" col="col-md-6" />
        <div class="col-md-6">
            <div class="form-label mb-1">{{ __('Also send by') }}</div>
            @foreach(['sms' => __('SMS'), 'email' => __('E-mail'), 'whatsapp' => __('WhatsApp')] as $ch => $label)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $ch }}" id="ch_{{ $ch }}" @checked(in_array($ch, $chosenChannels, true)) @disabled(! $canSend || ! in_array($ch, $enabled, true))>
                    <label class="form-check-label" for="ch_{{ $ch }}">{{ $label }}</label>
                </div>
            @endforeach
            <div class="form-text">{{ __('Everyone in the audience with a portal login gets an in-app notification. Switched-off channels are enabled in the SMS, e-mail & WhatsApp settings.') }}</div>
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
        <button class="btn btn-outline-primary" type="submit">{{ $notice->exists ? __('Save') : __('Save as draft') }}</button>
        @if(! $notice->exists && auth()->user()->can('notices.publish'))
            <button class="btn btn-brand" type="submit" name="publish_now" value="1">{{ __('Save and publish') }}</button>
        @endif
        <a href="{{ $notice->exists ? route('branch.communication.notices.show', $notice) : route('branch.communication.notices.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
