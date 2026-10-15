@extends('layouts.portal')
@section('title', $assignment->title)

@section('content')
<x-page-header :title="$assignment->title" :breadcrumbs="[__('Homework & progress') => route($routePrefix.'learning', $routeParams), $assignment->kindLabel() => null]" />

<div class="row g-3">
    <div class="col-lg-7">
        <div class="panel panel-body">
            <div class="row g-3 small mb-2">
                <div class="col-6"><div class="text-body-secondary">{{ __('Subject') }}</div><strong>{{ $assignment->subject?->displayName() ?? '—' }}</strong></div>
                <div class="col-6"><div class="text-body-secondary">{{ __('Teacher') }}</div><strong>{{ $assignment->creator?->name ?? '—' }}</strong></div>
                <div class="col-6"><div class="text-body-secondary">{{ __('Due') }}</div><strong @class(['text-danger' => $assignment->isPastDue() && ! $submission?->submitted_at])>{{ $assignment->due_at->format('d M Y H:i') }}</strong></div>
                <div class="col-6"><div class="text-body-secondary">{{ __('Full marks') }}</div><strong>{{ $assignment->isGraded() ? $assignment->max_marks + 0 : __('Not graded') }}</strong></div>
            </div>
            @if($assignment->instructions)<hr><div style="white-space: pre-wrap">{{ $assignment->instructions }}</div>@endif
            @if($assignment->attachment_name)<hr><a href="{{ route($routePrefix.'assignments.attachment', [...$routeParams, $assignment]) }}"><i class="bi bi-paperclip"></i> {{ $assignment->attachment_name }}</a>@endif
            @if($assignment->status === 'closed')<div class="alert alert-secondary mt-3 mb-0">{{ __('This assignment is closed.') }}</div>@endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="panel panel-body">
            <h2 class="h6">{{ $mode === 'parent' ? __('Hand-in') : __('Your hand-in') }}</h2>
            @if($submission && ($submission->submitted_at || $submission->status === 'evaluated'))
                <div class="mb-2">
                    <span class="badge text-bg-{{ $submission->tone() }}">{{ $submission->status === 'evaluated' ? __('Evaluated') : ($submission->status === 'returned' ? __('Returned for revision') : __('Handed in')) }}</span>
                    @if($submission->is_late)<span class="badge text-bg-danger">{{ __('Late') }}</span>@endif
                    @if($submission->submitted_at)<span class="small text-body-secondary ms-1">{{ $submission->submitted_at->format('d M Y H:i') }}</span>@endif
                </div>
                @if($submission->file_name)<div class="mb-2"><a href="{{ route($routePrefix.'assignments.submission-file', [...$routeParams, $assignment]) }}"><i class="bi bi-paperclip"></i> {{ $submission->file_name }}</a></div>@endif
                @if($submission->text)<div class="border rounded p-2 mb-2 bg-body-tertiary" style="white-space: pre-wrap">{{ $submission->text }}</div>@endif
                @if($submission->status === 'evaluated' || $submission->feedback)
                    <div class="border-start border-3 border-success ps-3 mb-2">
                        @if($submission->marks !== null)<div class="h5 mb-1">{{ $submission->marks + 0 }} / {{ $assignment->max_marks + 0 }}</div>@endif
                        @if($submission->feedback)<div><span class="text-body-secondary small">{{ __('Teacher’s feedback') }}</span><br>{{ $submission->feedback }}</div>@endif
                    </div>
                @endif
            @elseif($assignment->submission_mode === 'none')
                <p class="text-body-secondary">{{ __('This work is done in class or on paper — nothing to hand in online.') }}</p>
            @else
                <p class="text-body-secondary">{{ __('Nothing handed in yet.') }}</p>
            @endif

            @if($canSubmit)
                <hr>
                <form method="POST" enctype="multipart/form-data" action="{{ route('portal.student.assignments.submit', $assignment) }}">
                    @csrf
                    @if(in_array($assignment->submission_mode, ['text', 'either'], true))
                        <label class="form-label" for="text">{{ __('Your answer') }}</label>
                        <textarea id="text" name="text" rows="6" maxlength="20000" class="form-control mb-2 @error('text') is-invalid @enderror">{{ old('text', $submission?->text) }}</textarea>
                        @error('text')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                    @endif
                    @if(in_array($assignment->submission_mode, ['file', 'either'], true))
                        <label class="form-label" for="file">{{ __('Attach a file (max 10 MB)') }}</label>
                        <input id="file" type="file" name="file" class="form-control mb-2 @error('file') is-invalid @enderror" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.png,.jpg,.jpeg,.webp,.zip">
                        @error('file')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        @if($assignment->submission_mode === 'file')@error('text')<div class="text-danger small mb-2">{{ $message }}</div>@enderror @endif
                    @endif
                    @if($assignment->isPastDue())<div class="alert alert-warning small py-2">{{ __('The due date has passed. Your work will be marked as late.') }}</div>@endif
                    <button class="btn btn-brand">{{ $submission?->submitted_at ? __('Hand in again') : __('Hand in') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
