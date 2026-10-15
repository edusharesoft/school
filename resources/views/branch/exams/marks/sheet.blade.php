@extends($layout)
@section('title', __('Marks').' — '.$paper->subject?->name)

@php($labels = ['theory' => __('Theory'), 'practical' => __('Practical'), 'ca' => __('CA')])

@section('content')
<x-page-header :title="$paper->subject?->name.' — '.$paper->schoolClass?->name.' '.$section->name"
    :breadcrumbs="[__('Marks entry') => route($routePrefix.'exams.marks.index', ['exam_id' => $exam->id]), $exam->name => null]" />

<div class="card mb-3">
    <div class="card-body small d-flex flex-wrap gap-4">
        <div><span class="text-body-secondary">{{ __('Exam') }}:</span> <strong>{{ $exam->name }}</strong></div>
        <div><span class="text-body-secondary">{{ __('Full marks') }}:</span> <strong>{{ (float) $paper->full_marks }}</strong></div>
        @foreach($components as $name => $c)
            <div><span class="text-body-secondary">{{ $labels[$name] }}:</span> {{ (float) $c['full'] }} @if((float) $c['pass'] > 0)({{ __('pass') }} {{ (float) $c['pass'] }})@endif</div>
        @endforeach
        <div><span class="text-body-secondary">{{ __('Pass (total)') }}:</span> {{ (float) $paper->pass_marks }}</div>
        @if($paper->studyGroup)<div><span class="text-body-secondary">{{ __('Group') }}:</span> {{ $paper->studyGroup->name }}</div>@endif
    </div>
</div>

@if(! $editable && ! $correctable)
    <div class="alert alert-secondary small">{{ __('Read only.') }}</div>
@elseif($correctable)
    <div class="alert alert-warning small"><i class="bi bi-lock me-1"></i>{{ __('Results are being processed, so marks are locked. Use "Correct" on a row to request a change; it takes effect after approval.') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route($routePrefix.'exams.marks.store', [$exam, $paper, $section]) }}" class="card" id="marks-form">
    @csrf
    @if($candidates->isEmpty())
        <x-empty-state icon="people" :title="__('No students in this section sit this paper')" />
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:70px">{{ __('Roll') }}</th><th>{{ __('Student') }}</th>
                        @foreach($components as $name => $c)<th class="text-center" style="width:110px">{{ $labels[$name] }} <span class="fw-normal text-body-secondary">/{{ (float) $c['full'] }}</span></th>@endforeach
                        <th class="text-center" style="width:80px">{{ __('Absent') }}</th>
                        <th class="text-center" style="width:80px">{{ __('Total') }}</th>
                        @if($correctable)<th style="width:110px"></th>@endif
                    </tr>
                </thead>
                <tbody>
                @foreach($candidates as $en)
                    @php($m = $marks->get($en->student_id))
                    @php($key = 'entries.'.$en->student_id)
                    <tr @class(['table-secondary' => in_array($en->status, App\Services\Exams\MarkEntryService::LEFT, true)])>
                        <td>{{ $en->roll_no }}</td>
                        <td><div class="fw-semibold">{{ $en->student?->name }}</div><div class="small text-body-secondary">{{ $en->student?->student_code }}</div></td>
                        @foreach($components as $name => $c)
                            <td>
                                <input type="number" step="0.5" min="0" max="{{ (float) $c['full'] }}" inputmode="decimal"
                                    name="entries[{{ $en->student_id }}][{{ $name }}]" value="{{ old("$key.$name", $m?->marks[$name] ?? '') }}"
                                    @class(['form-control form-control-sm text-end mark-input', 'is-invalid' => $errors->has("$key.$name")])
                                    @disabled(! $editable) aria-label="{{ $en->student?->name }} {{ $labels[$name] }}">
                            </td>
                        @endforeach
                        <td class="text-center"><input type="checkbox" class="form-check-input" name="entries[{{ $en->student_id }}][absent]" value="1"
                            @checked(old("$key.absent", $m?->is_absent)) @disabled(! $editable) aria-label="{{ __('Absent') }}"></td>
                        <td class="text-center fw-semibold">{{ $m ? ($m->is_absent ? __('Abs') : ($m->total !== null ? (float) $m->total : '…')) : '—' }}</td>
                        @if($correctable)
                            <td class="text-end">
                                @if($m && $m->corrections->isNotEmpty())
                                    <span class="badge text-bg-warning">{{ __('Pending') }}</span>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#correctModal"
                                        data-student="{{ $en->student_id }}" data-name="{{ $en->student?->name }}"
                                        data-absent="{{ $m?->is_absent ? 1 : 0 }}" data-marks='@json($m?->marks ?? [])'>{{ __('Correct') }}</button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($editable)
            <div class="card-footer bg-transparent d-flex flex-wrap gap-2 align-items-center">
                <button class="btn btn-brand" type="submit"><i class="bi bi-check2 me-1"></i>{{ __('Save marks') }}</button>
                <span class="small text-body-secondary">{{ __('Leave a box empty if the mark is not known yet; clearing all boxes of a student removes the entry. Enter moves to the next box.') }}</span>
            </div>
        @endif
    @endif
</form>

@if($correctable)
<div class="modal fade" id="correctModal" tabindex="-1" aria-labelledby="correctTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route($routePrefix.'exams.corrections.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="examination_subject_id" value="{{ $paper->id }}">
            <input type="hidden" name="student_id" id="cm_student">
            <div class="modal-header"><h2 class="modal-title h6" id="correctTitle">{{ __('Request a mark correction') }}: <span id="cm_name"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body row g-2">
                @foreach($components as $name => $c)
                    <div class="col-4">
                        <label class="form-label small" for="cm_{{ $name }}">{{ $labels[$name] }} /{{ (float) $c['full'] }}</label>
                        <input type="number" step="0.5" min="0" max="{{ (float) $c['full'] }}" name="marks[{{ $name }}]" id="cm_{{ $name }}" class="form-control form-control-sm">
                    </div>
                @endforeach
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="absent" value="1" id="cm_absent"><label class="form-check-label" for="cm_absent">{{ __('Absent') }}</label></div></div>
                <div class="col-12">
                    <label class="form-label small" for="cm_reason">{{ __('Reason') }}</label>
                    <textarea name="reason" id="cm_reason" rows="2" class="form-control" required minlength="5" maxlength="500"></textarea>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-brand">{{ __('Send request') }}</button></div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Enter jumps to the next mark box instead of submitting the form.
    const inputs = [...document.querySelectorAll('#marks-form .mark-input:not([disabled])')];
    inputs.forEach((el, i) => el.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); (inputs[i + 1] || el).focus(); inputs[i + 1]?.select(); }
    }));
    const modal = document.getElementById('correctModal');
    modal?.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget, marks = JSON.parse(b.dataset.marks || '{}');
        modal.querySelector('#cm_student').value = b.dataset.student;
        modal.querySelector('#cm_name').textContent = b.dataset.name;
        modal.querySelector('#cm_absent').checked = b.dataset.absent === '1';
        modal.querySelectorAll('input[name^="marks["]').forEach((inp) => { const k = inp.name.slice(6, -1); inp.value = marks[k] ?? ''; });
    });
});
</script>
@endpush
