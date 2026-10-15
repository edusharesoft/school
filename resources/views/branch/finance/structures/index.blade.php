@extends('layouts.branch')
@section('title', __('Fee structure'))

@php
    $canEdit = auth()->user()->can('fees.update');
    $monthNames = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => __(\Carbon\Carbon::create(2000, $m, 1)->format('M'))]);
@endphp

@section('content')
<x-page-header :title="__('Fee structure')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Fee structure') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.finance.heads.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1"></i>{{ __('Fee heads') }}</a>
    </x-slot:actions>
</x-page-header>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small" for="f_s">{{ __('Session') }}</label>
                <select id="f_s" name="academic_session_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($sessions as $s)<option value="{{ $s->id }}" @selected($session?->id === $s->id)>{{ $s->name }}{{ $s->is_current ? ' ★' : '' }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label small" for="f_c">{{ __('Class') }}</label>
                <select id="f_c" name="school_class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($classes as $c)<option value="{{ $c->id }}" @selected($class?->id === $c->id)>{{ $c->name }}</option>@endforeach
                </select></div>
            @if($class?->has_groups)
                <div class="col-md-3"><label class="form-label small" for="f_g">{{ __('Group') }}</label>
                    <select id="f_g" name="study_group_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">{{ __('All groups') }}</option>
                        @foreach($groups as $id => $name)<option value="{{ $id }}" @selected($groupId === $id)>{{ $name }}</option>@endforeach
                    </select></div>
            @endif
        </form>
    </div>
</div>

@if(! $session || ! $class)
    <div class="card"><x-empty-state icon="grid-3x3" :title="__('Create an academic session and classes first.')" /></div>
@elseif($heads->isEmpty())
    <div class="card"><x-empty-state icon="tags" :title="__('No fee heads yet')">
        <a href="{{ route('branch.finance.heads.index') }}" class="btn btn-brand btn-sm mt-2">{{ __('Add fee heads') }}</a></x-empty-state></div>
@else
    <form method="POST" action="{{ route('branch.finance.structures.store') }}" class="card mb-3">
        @csrf
        <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
        <input type="hidden" name="school_class_id" value="{{ $class->id }}">
        <input type="hidden" name="study_group_id" value="{{ $groupId }}">
        <div class="card-header bg-transparent fw-semibold">{{ $class->name }} · {{ $session->name }}@if($groupId) · {{ $groups[$groupId] ?? '' }}@endif</div>
        @if($errors->any())<div class="alert alert-danger m-3 mb-0 small"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>{{ __('Fee head') }}</th><th style="width:130px">{{ __('Amount (৳)') }}</th><th>{{ __('Billed in') }}</th></tr></thead>
                <tbody>
                @foreach($heads as $h)
                    @php
                        $row = $rows->get($h->id);
                        $months = old("heads.{$h->id}.months", $row?->months ?? ($h->category === 'tuition' ? range(1, 12) : []));
                    @endphp
                    <tr>
                        <td><div class="fw-semibold">{{ $h->name }}</div><div class="small text-body-secondary">{{ __(ucfirst($h->category)) }}</div></td>
                        <td><input type="number" step="0.01" min="0" name="heads[{{ $h->id }}][amount]" value="{{ old("heads.{$h->id}.amount", $row ? (float) $row->amount : '') }}"
                            class="form-control form-control-sm text-end" @disabled(! $canEdit) aria-label="{{ $h->name }} {{ __('Amount') }}"></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2 small" data-months>
                                @foreach($monthNames as $m => $label)
                                    <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" name="heads[{{ $h->id }}][months][]" value="{{ $m }}"
                                        @checked(in_array($m, array_map('intval', (array) $months), true)) @disabled(! $canEdit)>{{ $label }}</label>
                                @endforeach
                                @if($canEdit)
                                    <button type="button" class="btn btn-link btn-sm p-0" data-all>{{ __('All') }}</button>
                                    <button type="button" class="btn btn-link btn-sm p-0" data-none>{{ __('None') }}</button>
                                @endif
                            </div>
                            @error("heads.{$h->id}.months")<div class="text-danger small">{{ $message }}</div>@enderror
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($canEdit)
            <div class="card-footer bg-transparent d-flex flex-wrap gap-2 align-items-center">
                <button class="btn btn-brand"><i class="bi bi-check2 me-1"></i>{{ __('Save fee structure') }}</button>
                <span class="small text-body-secondary">{{ __('Leave the amount empty for fees this class does not pay. Monthly tuition: tick every month; session or exam fees: only the month they are billed in.') }}</span>
            </div>
        @endif
    </form>

    @if($canEdit && $rows->isNotEmpty())
        <form method="POST" action="{{ route('branch.finance.structures.copy') }}" class="card mb-3" data-confirm="{{ __('Replace the fee structure of the selected classes with this one?') }}">
            @csrf
            <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
            <input type="hidden" name="from_class_id" value="{{ $class->id }}">
            <div class="card-body d-flex flex-wrap align-items-center gap-3 small">
                <span class="fw-semibold">{{ __('Copy to') }}:</span>
                @foreach($classes->where('id', '!=', $class->id) as $c)
                    <label><input type="checkbox" class="form-check-input me-1" name="to_class_ids[]" value="{{ $c->id }}">{{ $c->name }}</label>
                @endforeach
                <button class="btn btn-sm btn-outline-secondary ms-auto">{{ __('Copy structure') }}</button>
            </div>
        </form>
    @endif

    <div class="card">
        <div class="card-header bg-transparent fw-semibold">{{ __('All classes — :session', ['session' => $session->name]) }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 small">
                <thead><tr><th>{{ __('Class') }}</th><th>{{ __('Fees') }}</th><th class="text-end">{{ __('Yearly total (৳)') }}</th></tr></thead>
                <tbody>
                @forelse($summary->sortBy(fn ($r) => $r->first()->schoolClass?->numeric_order) as $classRows)
                    <tr>
                        <td class="fw-semibold">{{ $classRows->first()->schoolClass?->name }}</td>
                        <td>@foreach($classRows as $r)<span class="badge text-bg-light border me-1">{{ $r->head?->name }}: {{ number_format($r->amount, 0) }} × {{ count($r->months ?? []) }}</span>@endforeach</td>
                        <td class="text-end">{{ number_format($classRows->sum(fn ($r) => $r->amount * count($r->months ?? [])), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-body-secondary">{{ __('No fee structure yet for this session.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-months]').forEach((box) => {
    box.querySelector('[data-all]')?.addEventListener('click', () => box.querySelectorAll('input[type=checkbox]').forEach((c) => { c.checked = true; }));
    box.querySelector('[data-none]')?.addEventListener('click', () => box.querySelectorAll('input[type=checkbox]').forEach((c) => { c.checked = false; }));
});
</script>
@endpush
