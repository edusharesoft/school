@extends($layout)
@section('title', $assignment->title)

@section('content')
<x-page-header :title="$assignment->title" :breadcrumbs="[__('Homework & assignments') => route($routePrefix.'learning.assignments.index'), $assignment->kindLabel() => null]">
    <x-slot:actions>
        @if($canManage)
            @if($assignment->status !== 'closed')<a href="{{ route($routePrefix.'learning.assignments.edit', $assignment) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>@endif
            @if($assignment->status === 'draft')
                <form method="POST" action="{{ route($routePrefix.'learning.assignments.publish', $assignment) }}" data-confirm="{{ __('Publish now? Students and guardians will be notified.') }}">@csrf
                    <button class="btn btn-brand btn-sm"><i class="bi bi-send me-1"></i>{{ __('Publish') }}</button></form>
            @elseif($assignment->status === 'published')
                <form method="POST" action="{{ route($routePrefix.'learning.assignments.close', $assignment) }}" data-confirm="{{ __('Close this assignment? Students can no longer hand in.') }}">@csrf
                    <button class="btn btn-outline-warning btn-sm">{{ __('Close') }}</button></form>
            @else
                <form method="POST" action="{{ route($routePrefix.'learning.assignments.reopen', $assignment) }}">@csrf<button class="btn btn-outline-primary btn-sm">{{ __('Reopen') }}</button></form>
            @endif
            @if($assignment->status === 'draft' || $roster->every(fn ($r) => ! $r['submission']))
                <form method="POST" action="{{ route($routePrefix.'learning.assignments.destroy', $assignment) }}" data-confirm="{{ __('Delete this item?') }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
            @endif
        @endif
    </x-slot:actions>
</x-page-header>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 small">
            <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Class') }}</div><strong>{{ $assignment->schoolClass?->name }}@if($assignment->section) — {{ $assignment->section->name }}@endif</strong></div>
            <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Subject') }}</div><strong>{{ $assignment->subject?->displayName() ?? '—' }}</strong></div>
            <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Due') }}</div><strong @class(['text-danger' => $assignment->status === 'published' && $assignment->isPastDue()])>{{ $assignment->due_at->format('d M Y H:i') }}</strong>@if($assignment->allow_late)<div class="text-body-secondary">{{ __('Late hand-in accepted') }}</div>@endif</div>
            <div class="col-6 col-md-3"><div class="text-body-secondary">{{ __('Status') }}</div><span class="badge text-bg-{{ ['draft' => 'secondary', 'published' => 'success', 'closed' => 'dark'][$assignment->status] }}">{{ __(ucfirst($assignment->status)) }}</span>
                @if($assignment->isGraded())<span class="badge text-bg-light border">{{ __('Full marks') }} {{ $assignment->max_marks + 0 }}</span>@endif</div>
        </div>
        @if($assignment->instructions)<hr><div style="white-space: pre-wrap">{{ $assignment->instructions }}</div>@endif
        @if($assignment->attachment_name)<hr><a href="{{ route($routePrefix.'learning.assignments.attachment', $assignment) }}"><i class="bi bi-paperclip"></i> {{ $assignment->attachment_name }}</a>@endif
    </div>
</div>

@if($assignment->status === 'draft')
    <div class="alert alert-info">{{ __('This is a draft. Students see it only after you publish it.') }}</div>
@else
<form method="POST" action="{{ route($routePrefix.'learning.assignments.evaluate', $assignment) }}" class="card">
    @csrf
    <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2">
        <strong>{{ __('Students') }}</strong>
        <span class="badge text-bg-light border">{{ __('Handed in') }} {{ bn_digits($roster->filter(fn ($r) => $r['submission']?->submitted_at)->count()) }} / {{ bn_digits($roster->count()) }}</span>
        <span class="badge text-bg-light border">{{ __('Evaluated') }} {{ bn_digits($roster->filter(fn ($r) => $r['submission']?->status === 'evaluated')->count()) }}</span>
    </div>
    @if($roster->isEmpty())
        <x-empty-state icon="people" :title="__('No students are enrolled in this class yet.')" />
    @else
    <div class="table-responsive">
        <table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Roll') }}</th><th>{{ __('Student') }}</th><th>{{ __('Hand-in') }}</th>@if($canEvaluate)<th style="width:110px">{{ __('Marks') }}@if($assignment->isGraded()) / {{ $assignment->max_marks + 0 }}@endif</th><th>{{ __('Feedback') }}</th><th style="width:150px">{{ __('Action') }}</th>@else<th>{{ __('Marks') }}</th><th>{{ __('Feedback') }}</th>@endif</tr></thead>
            <tbody>
            @foreach($roster as $r)
                @php($s = $r['student'])
                @php($sub = $r['submission'])
                <tr>
                    <td>{{ bn_digits($s->currentEnrollment?->roll_no) }}</td>
                    <td class="fw-semibold">{{ $s->name }}<div class="text-body-secondary fw-normal">{{ $s->student_code }}</div></td>
                    <td>
                        @if($sub?->submitted_at)
                            <span class="badge text-bg-{{ $sub->tone() }}">{{ $sub->status === 'evaluated' ? __('Evaluated') : ($sub->status === 'returned' ? __('Returned') : __('Handed in')) }}</span>
                            @if($sub->is_late)<span class="badge text-bg-danger">{{ __('Late') }}</span>@endif
                            <div class="text-body-secondary">{{ $sub->submitted_at->format('d M H:i') }}@if($sub->attempts > 1) · {{ __('attempt') }} {{ bn_digits($sub->attempts) }}@endif</div>
                            @if($sub->file_name)<a href="{{ route($routePrefix.'learning.assignments.submission-file', [$assignment, $sub]) }}"><i class="bi bi-paperclip"></i> {{ $sub->file_name }}</a>@endif
                            @if($sub->text)<details><summary class="text-primary" style="cursor:pointer">{{ __('Read answer') }}</summary><div style="white-space: pre-wrap" class="border rounded p-2 mt-1 bg-body-tertiary">{{ $sub->text }}</div></details>@endif
                        @elseif($sub?->status === 'evaluated')
                            <span class="badge text-bg-success">{{ __('Evaluated') }}</span> <span class="text-body-secondary">{{ __('(no hand-in)') }}</span>
                        @else
                            <span class="text-body-secondary">{{ $assignment->submission_mode === 'none' ? __('Not marked yet') : __('Not handed in') }}</span>
                        @endif
                    </td>
                    @if($canEvaluate)
                        <td><input type="number" step="0.01" min="0" @if($assignment->isGraded()) max="{{ $assignment->max_marks + 0 }}" @else disabled @endif name="rows[{{ $s->id }}][marks]" value="{{ old("rows.{$s->id}.marks", $sub?->marks !== null ? $sub->marks + 0 : '') }}" class="form-control form-control-sm @error("rows.{$s->id}.marks") is-invalid @enderror" aria-label="{{ __('Marks') }}">
                            @error("rows.{$s->id}.marks")<div class="invalid-feedback">{{ $message }}</div>@enderror</td>
                        <td><input type="text" maxlength="2000" name="rows[{{ $s->id }}][feedback]" value="{{ old("rows.{$s->id}.feedback", $sub?->feedback) }}" class="form-control form-control-sm" aria-label="{{ __('Feedback') }}"></td>
                        <td><select name="rows[{{ $s->id }}][action]" class="form-select form-select-sm" aria-label="{{ __('Action') }}">
                            <option value="skip">{{ __('No change') }}</option>
                            <option value="evaluate" @selected($sub?->status === 'evaluated')>{{ __('Save evaluation') }}</option>
                            @if($sub?->submitted_at)<option value="return" @selected($sub->status === 'returned')>{{ __('Return for revision') }}</option>@endif
                        </select>@error("rows.{$s->id}.action")<div class="text-danger">{{ $message }}</div>@enderror</td>
                    @else
                        <td>{{ $sub?->marks !== null ? $sub->marks + 0 : '—' }}</td><td>{{ $sub?->feedback }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($canEvaluate)<div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Save evaluation') }}</button>
        <span class="small text-body-secondary ms-2">{{ __('Only rows with an action other than “No change” are saved. Students and guardians are notified.') }}</span></div>@endif
    @endif
</form>
@endif
@endsection
