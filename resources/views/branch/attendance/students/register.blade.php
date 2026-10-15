@extends($layout)
@section('title', __('Register').' — '.$section->label())

@section('content')
<x-page-header :title="__('Register').': '.$section->label()"
    :breadcrumbs="[__('Attendance') => route($routePrefix.'attendance.students.index', ['date' => $date->toDateString()]), $section->label() => null]" />

<form method="GET" class="panel panel-body mb-4 d-flex flex-wrap align-items-end gap-2">
    <div><label class="form-label small mb-1" for="date">{{ __('Date') }}</label>
        <input type="date" id="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
    <div><label class="form-label small mb-1" for="subject_id">{{ __('Register') }}</label>
        <select id="subject_id" name="subject_id" class="form-select form-select-sm">
            <option value="">{{ __('Daily register') }}</option>
            @foreach($subjects as $sub)<option value="{{ $sub->id }}" @selected($subjectId === $sub->id)>{{ __('Subject') }}: {{ $sub->name }}</option>@endforeach
        </select></div>
    <button class="btn btn-brand btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Show') }}</button>
</form>

@if($markableReason)
    <div class="alert alert-warning"><i class="fa-solid fa-circle-info me-1"></i> {{ $markableReason }}</div>
@elseif($taken && ! $editable && ! $date->isToday())
    <div class="alert alert-info"><i class="fa-solid fa-lock me-1"></i> {{ __('This register is closed. Use the pencil button to request a correction.') }}</div>
@endif

@if($rows->isEmpty())
    <div class="card"><x-empty-state icon="people" :title="__('No enrolled students in this section for the current session')" /></div>
@else
<form method="POST" action="{{ route($routePrefix.'attendance.students.store') }}" class="card"
      x-data="{ setAll(s) { document.querySelectorAll('input[data-st=' + s + ']').forEach(el => el.checked = true); $refs.method.value = 'bulk'; } }">
    @csrf
    <input type="hidden" name="section_id" value="{{ $section->id }}">
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    @if($subjectId)<input type="hidden" name="subject_id" value="{{ $subjectId }}">@endif
    <input type="hidden" name="method" value="manual" x-ref="method">

    @if($editable)
        <div class="card-body border-bottom d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-body-secondary me-2">{{ __('Mark everyone') }}:</span>
            <button type="button" class="btn btn-sm btn-outline-success" x-on:click="setAll('present')"><i class="fa-solid fa-check-double me-1"></i>{{ __('Present') }}</button>
            <button type="button" class="btn btn-sm btn-outline-danger" x-on:click="setAll('absent')">{{ __('Absent') }}</button>
            <span class="ms-auto small text-body-secondary">{{ bn_digits($rows->count()) }} {{ __('students') }}</span>
        </div>
    @endif
    @error('entries')<div class="alert alert-danger m-3">{{ $message }}</div>@enderror
    @error('date')<div class="alert alert-danger m-3">{{ $message }}</div>@enderror

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th style="width:70px">{{ __('Roll') }}</th><th>{{ __('Student') }}</th><th>{{ __('Status') }}</th><th style="width:220px">{{ __('Remarks') }}</th><th></th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                @php
                    $e = $row['enrollment'];
                    $rec = $row['record'];
                    $current = old('entries.'.$e->student_id.'.status', $rec?->status->value ?? 'present');
                @endphp
                <tr>
                    <td class="fw-semibold">{{ bn_digits($e->roll_no) }}</td>
                    <td><div class="fw-medium">{{ $e->student?->name }}</div><code class="small">{{ $e->student?->student_code }}</code>
                        @if($rec?->check_in_at)<span class="small text-body-secondary ms-1"><i class="fa-regular fa-clock"></i> {{ substr($rec->check_in_at, 0, 5) }} ({{ $rec->method }})</span>@endif</td>
                    <td>
                        @if($editable)
                            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="{{ __('Status') }}">
                                @foreach($statuses as $st)
                                    <input type="radio" class="btn-check" name="entries[{{ $e->student_id }}][status]" id="s{{ $e->student_id }}{{ $st->value }}" value="{{ $st->value }}" data-st="{{ $st->value }}" @checked($current === $st->value) autocomplete="off">
                                    <label class="btn btn-outline-{{ $st->tone() }}" for="s{{ $e->student_id }}{{ $st->value }}" title="{{ $st->label() }}">{{ $st->short() }}</label>
                                @endforeach
                            </div>
                        @elseif($rec)
                            <span class="badge text-bg-{{ $rec->status->tone() }}">{{ $rec->status->label() }}</span>
                        @else
                            <span class="text-body-secondary">—</span>
                        @endif
                    </td>
                    <td>
                        @if($editable)
                            <input name="entries[{{ $e->student_id }}][remarks]" value="{{ old('entries.'.$e->student_id.'.remarks', $rec?->remarks) }}" class="form-control form-control-sm" maxlength="255" aria-label="{{ __('Remarks') }}">
                        @else
                            <span class="small">{{ $rec?->remarks }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($rec && ! $editable && $canCorrect)
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#correctionModal"
                                data-record="{{ $rec->id }}" data-type="student" data-name="{{ $e->student?->name }}" data-status="{{ $rec->status->value }}" title="{{ __('Request correction') }}"><i class="bi bi-pencil"></i></button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($editable)
        <div class="card-footer bg-transparent d-flex gap-2 align-items-center">
            <button class="btn btn-brand"><i class="fa-solid fa-floppy-disk me-1"></i>{{ $taken ? __('Update register') : __('Save register') }}</button>
            <span class="small text-body-secondary">P = {{ __('Present') }}, A = {{ __('Absent') }}, L = {{ __('Late') }}, H = {{ __('Half-day') }}, LV = {{ __('Leave') }}, E = {{ __('Excused absence') }}</span>
        </div>
    @endif
</form>
@endif

@include('branch.attendance.partials.correction-modal', ['correctionRoute' => route($routePrefix.'attendance.corrections.store'), 'withTimes' => false])
@endsection
