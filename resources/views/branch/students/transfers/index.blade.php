@extends('layouts.branch')
@section('title', __('Branch transfers'))

@section('content')
<x-page-header :title="__('Transfers between branches')" :breadcrumbs="[__('Students') => route('branch.students.index'), __('Transfers') => null]" />

<div class="alert alert-light border small"><i class="fa-solid fa-circle-info text-primary me-1"></i>
    {{ __('Start a transfer from the student’s profile. The receiving branch accepts it and places the student in a class; the student gets a new ID there and this branch keeps the full history.') }}</div>

<div class="card">
    @if($transfers->isEmpty())
        <x-empty-state icon="arrow-left-right" :title="__('No transfers yet')" />
    @else
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>{{ __('Direction') }}</th><th>{{ __('Student') }}</th><th>{{ __('From → to') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                <tbody>
                @foreach($transfers as $t)
                    @php($incoming = $t->to_branch_id === $branchId)
                    <tr>
                        <td>@if($incoming)<span class="badge text-bg-primary"><i class="fa-solid fa-arrow-right-to-bracket"></i> {{ __('Incoming') }}</span>@else<span class="badge text-bg-secondary"><i class="fa-solid fa-arrow-right-from-bracket"></i> {{ __('Outgoing') }}</span>@endif</td>
                        <td><div class="fw-medium">{{ $t->sourceStudent?->name }}</div><code class="small">{{ $t->sourceStudent?->student_code }}</code>
                            @if($t->newStudent)<div class="small">→ <code>{{ $t->newStudent->student_code }}</code></div>@endif</td>
                        <td class="small">{{ $t->fromBranch?->name }} → {{ $t->toBranch?->name }}<div class="text-body-secondary">{{ $t->requested_on->format('d M Y') }} · {{ $t->requester?->name }}</div></td>
                        <td class="small" style="max-width:260px">{{ $t->reason }}@if($t->decision_note)<div class="text-body-secondary fst-italic">{{ $t->decision_note }}</div>@endif</td>
                        <td><span class="badge text-bg-{{ $t->tone() }}">{{ __(ucfirst($t->status)) }}</span></td>
                        <td class="text-end text-nowrap">
                            @if($t->isPending() && $incoming)
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="collapse" data-bs-target="#acc{{ $t->id }}">{{ __('Accept') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rej{{ $t->id }}">{{ __('Reject') }}</button>
                            @elseif($t->isPending())
                                <form method="POST" action="{{ route('branch.transfers.cancel', $t) }}" data-confirm="{{ __('Cancel this transfer request?') }}">@csrf
                                    <button class="btn btn-sm btn-outline-secondary">{{ __('Cancel') }}</button></form>
                            @endif
                        </td>
                    </tr>
                    @if($t->isPending() && $incoming)
                        <tr class="collapse" id="acc{{ $t->id }}"><td colspan="6">
                            <form method="POST" action="{{ route('branch.transfers.accept', $t) }}" class="row g-2 align-items-end"
                                  x-data="{ cls: '{{ $classes->first()?->id }}', classes: @js($classes->mapWithKeys(fn ($c) => [$c->id => ['groups' => (bool) $c->has_groups, 'sections' => $c->sections->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()]])) }">
                                @csrf
                                <div class="col-md-3"><label class="form-label small">{{ __('Session') }}</label>
                                    <select name="academic_session_id" class="form-select form-select-sm">@foreach($sessions as $s)<option value="{{ $s->id }}" @selected($s->is_current)>{{ $s->name }}</option>@endforeach</select></div>
                                <div class="col-md-3"><label class="form-label small">{{ __('Class') }}</label>
                                    <select name="school_class_id" class="form-select form-select-sm" x-model="cls">@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                                <div class="col-md-2"><label class="form-label small">{{ __('Section') }}</label>
                                    <select name="section_id" class="form-select form-select-sm"><template x-for="s in (classes[cls]?.sections || [])" :key="s.id"><option :value="s.id" x-text="s.name"></option></template></select></div>
                                <div class="col-md-2" x-show="classes[cls]?.groups"><label class="form-label small">{{ __('Group') }}</label>
                                    <select name="study_group_id" class="form-select form-select-sm"><option value="">—</option>@foreach($groups as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                                <div class="col-md-2"><button class="btn btn-sm btn-success w-100">{{ __('Admit here') }}</button></div>
                            </form>
                        </td></tr>
                        <tr class="collapse" id="rej{{ $t->id }}"><td colspan="6">
                            <form method="POST" action="{{ route('branch.transfers.reject', $t) }}" class="d-flex gap-2">@csrf
                                <input name="note" class="form-control form-control-sm" placeholder="{{ __('Reason for rejection') }}" required minlength="3" maxlength="500" aria-label="{{ __('Reason for rejection') }}">
                                <button class="btn btn-sm btn-danger">{{ __('Reject') }}</button></form>
                        </td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $transfers->links() }}</div>
    @endif
</div>
@endsection
