@extends($layout)
@section('title', $assignment->exists ? __('Edit :kind', ['kind' => __($assignment->kind)]) : __('New :kind', ['kind' => __($assignment->kind)]))

@section('content')
<x-page-header :title="$assignment->exists ? __('Edit :kind', ['kind' => __($assignment->kind)]) : __('New :kind', ['kind' => __($assignment->kind)])"
    :breadcrumbs="[__('Homework & assignments') => route($routePrefix.'learning.assignments.index'), ($assignment->exists ? __('Edit') : __('New')) => null]" />

<form method="POST" enctype="multipart/form-data" class="card" style="max-width: 980px"
      action="{{ $assignment->exists ? route($routePrefix.'learning.assignments.update', $assignment) : route($routePrefix.'learning.assignments.store') }}"
      x-data="{ cls: @js((string) old('school_class_id', $assignment->school_class_id)), sections: @js($sections), subjects: @js($subjects), mode: @js(old('submission_mode', $assignment->submission_mode)) }">
    @csrf @if($assignment->exists) @method('PUT') @endif
    @unless($assignment->exists)<input type="hidden" name="kind" value="{{ $assignment->kind }}">@endunless
    <div class="card-body row g-3">
        <x-form.input name="title" :label="__('Title')" :value="$assignment->title" required col="col-md-8" maxlength="191" />
        <div class="col-md-4"><label class="form-label">{{ __('Type') }}</label><div class="form-control-plaintext fw-semibold">{{ $assignment->kindLabel() }}</div></div>

        <div class="col-md-4">
            <label class="form-label" for="school_class_id">{{ __('Class') }} <span class="text-danger">*</span></label>
            <select id="school_class_id" name="school_class_id" class="form-select @error('school_class_id') is-invalid @enderror" required x-model="cls">
                <option value="">{{ __('Choose…') }}</option>
                @foreach($classes as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
            </select>
            @error('school_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="section_id">{{ __('Section') }}@if($sectionRequired) <span class="text-danger">*</span>@endif</label>
            <select id="section_id" name="section_id" class="form-select @error('section_id') is-invalid @enderror" @required($sectionRequired)>
                @unless($sectionRequired)<option value="">{{ __('Whole class') }}</option>@endunless
                <template x-for="s in sections.filter(s => String(s.class) === String(cls))" :key="s.id">
                    <option :value="s.id" x-text="s.name" :selected="String(s.id) === @js((string) old('section_id', $assignment->section_id))"></option>
                </template>
            </select>
            @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="subject_id">{{ __('Subject') }}</label>
            <select id="subject_id" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror">
                <option value="">{{ __('General / none') }}</option>
                <template x-for="s in subjects.filter(s => String(s.class) === String(cls))" :key="s.id">
                    <option :value="s.id" x-text="s.name" :selected="String(s.id) === @js((string) old('subject_id', $assignment->subject_id))"></option>
                </template>
            </select>
            @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label" for="instructions">{{ __('Instructions') }}</label>
            <textarea id="instructions" name="instructions" rows="6" maxlength="10000" class="form-control @error('instructions') is-invalid @enderror">{{ old('instructions', $assignment->instructions) }}</textarea>
            @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <x-form.input name="due_at" type="datetime-local" :label="__('Due date & time')" :value="$assignment->due_at?->format('Y-m-d\TH:i')" required col="col-md-4" />
        <x-form.input name="max_marks" type="number" step="0.5" min="0.5" :label="__('Full marks (empty = not graded)')" :value="$assignment->max_marks !== null ? $assignment->max_marks + 0 : null" col="col-md-4" />
        <div class="col-md-4">
            <label class="form-label" for="submission_mode">{{ __('How students hand in') }}</label>
            <select id="submission_mode" name="submission_mode" class="form-select" x-model="mode">
                @foreach(['either' => __('File or written answer'), 'file' => __('File upload only'), 'text' => __('Written answer only'), 'none' => __('Not handed in online (done in class / on paper)')] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <x-form.switch name="allow_late" :label="__('Accept late hand-in (marked as late)')" :checked="$assignment->allow_late" col="col-md-6" />
        <div class="col-md-6">
            <label class="form-label" for="attachment">{{ __('Attachment (worksheet, reference — max 10 MB)') }}</label>
            <input id="attachment" name="attachment" type="file" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.png,.jpg,.jpeg,.webp">
            @if($assignment->attachment_name)
                <div class="form-check mt-1"><input type="hidden" name="remove_attachment" value="0"><input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="rm_att">
                    <label class="form-check-label" for="rm_att">{{ __('Remove the current file') }} ({{ $assignment->attachment_name }})</label></div>
            @endif
            @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
        <button class="btn btn-outline-primary" type="submit">{{ $assignment->exists ? __('Save') : __('Save as draft') }}</button>
        @if(! $assignment->exists)<button class="btn btn-brand" type="submit" name="publish_now" value="1">{{ __('Save and publish') }}</button>@endif
        <a href="{{ $assignment->exists ? route($routePrefix.'learning.assignments.show', $assignment) : route($routePrefix.'learning.assignments.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
