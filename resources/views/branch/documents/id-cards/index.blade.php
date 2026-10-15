@extends('layouts.branch')
@section('title', __('ID cards'))

@section('content')
<x-page-header :title="__('ID cards')" :breadcrumbs="[__('Documents') => null, __('ID cards') => null]">
    <x-slot:actions>
    <a href="{{ route('branch.idcards.register') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text me-1"></i>{{ __('Card register') }}</a>
    @can('settings.view')<a href="{{ route('branch.documents.settings') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-sliders me-1"></i>{{ __('Card design & signature') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link {{ $tab === 'students' ? 'active' : '' }}" href="{{ route('branch.idcards.index') }}">{{ __('Students') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'staff' ? 'active' : '' }}" href="{{ route('branch.idcards.index', ['tab' => 'staff']) }}">{{ __('Teachers & staff') }}</a></li>
</ul>

<form method="GET" class="card card-body mb-3 row g-2 flex-row align-items-end">
    <input type="hidden" name="tab" value="{{ $tab }}">
    @if($tab === 'students')
        <div class="col-md-3"><label class="form-label small mb-1" for="f_session">{{ __('Session') }}</label>
            <select id="f_session" name="session" class="form-select form-select-sm">
                @foreach($sessions as $s)<option value="{{ $s->id }}" @selected((int) ($filters['session'] ?? 0) === $s->id || (! ($filters['session'] ?? null) && $s->is_current))>{{ $s->name }}</option>@endforeach
            </select></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="f_class">{{ __('Class') }}</label>
            <select id="f_class" name="class" class="form-select form-select-sm"><option value="">{{ __('All classes') }}</option>
                @foreach($classes as $c)<option value="{{ $c->id }}" @selected((int) ($filters['class'] ?? 0) === $c->id)>{{ $c->name }}</option>@endforeach
            </select></div>
        <div class="col-md-3"><label class="form-label small mb-1" for="f_section">{{ __('Section') }}</label>
            <select id="f_section" name="section" class="form-select form-select-sm"><option value="">{{ __('All sections') }}</option>
                @foreach($sections as $s)<option value="{{ $s->id }}" @selected((int) ($filters['section'] ?? 0) === $s->id)>{{ $s->label() }}</option>@endforeach
            </select></div>
    @else
        <div class="col-md-3"><label class="form-label small mb-1" for="f_type">{{ __('Type') }}</label>
            <select id="f_type" name="type" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>
                <option value="teacher" @selected(($filters['type'] ?? '') === 'teacher')>{{ __('Teachers') }}</option>
                <option value="staff" @selected(($filters['type'] ?? '') === 'staff')>{{ __('Staff') }}</option>
            </select></div>
    @endif
    <div class="col-md-3"><label class="form-label small mb-1" for="f_q">{{ __('Search') }}</label>
        <input id="f_q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('Name or ID') }}"></div>
    <div class="col-auto"><button class="btn btn-sm btn-brand"><i class="bi bi-search"></i></button></div>
</form>

@if($rows->isEmpty())
    <div class="card"><x-empty-state icon="person-vcard" :title="__('Nobody to show')" :message="__('Change the filters or enrol students first.')" /></div>
@else
<form method="POST" action="{{ route('branch.idcards.print') }}" class="card" target="_blank" x-data="{ all: false }">
    @csrf
    <input type="hidden" name="tab" value="{{ $tab }}">
    @if($tab === 'students')<input type="hidden" name="session" value="{{ $filters['session'] ?? '' }}">@endif
    @can('id-cards.issue')
    <div class="card-body border-bottom d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-1" for="p_layout">{{ __('Layout') }}</label>
            <select id="p_layout" name="layout" class="form-select form-select-sm">
                <option value="sheet">{{ __('A4 sheet — 9 cards per page') }}</option>
                <option value="card">{{ __('One card per page (card printer)') }}</option>
            </select></div>
        <div><label class="form-label small mb-1" for="p_sides">{{ __('Sides') }}</label>
            <select id="p_sides" name="sides" class="form-select form-select-sm">
                <option value="both">{{ __('Front and back') }}</option>
                <option value="front">{{ __('Front only') }}</option>
            </select></div>
        <button class="btn btn-brand btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>{{ __('Print selected (PDF)') }}</button>
        <span class="small text-body-secondary">{{ __('Printing issues a card number to anyone who does not have one. Up to :n per print.', ['n' => $limit]) }}</span>
    </div>
    @endcan
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr>
                <th style="width:36px"><input type="checkbox" class="form-check-input" aria-label="{{ __('Select all') }}" x-model="all" @change="document.querySelectorAll('.pick').forEach(c => c.checked = all)"></th>
                @if($tab === 'students')
                    <th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Roll') }}</th>
                @else
                    <th>{{ __('Name') }}</th><th>{{ __('Designation') }}</th><th>{{ __('Department') }}</th>
                @endif
                <th>{{ __('ID card') }}</th><th class="text-end">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @foreach($rows as $row)
                @php($holder = $tab === 'students' ? $row['student'] : $row['employee'])
                @php($card = $row['card'])
                <tr>
                    <td><input type="checkbox" class="form-check-input pick" name="ids[]" value="{{ $holder->id }}" aria-label="{{ __('Select') }} {{ $holder->name }}"></td>
                    @if($tab === 'students')
                        <td>{{ $holder->name }} <code class="small">{{ $holder->student_code }}</code></td>
                        <td>{{ $row['enrollment']->schoolClass?->name }} · {{ $row['enrollment']->section?->name }}</td>
                        <td>{{ $row['enrollment']->roll_no }}</td>
                    @else
                        <td>{{ $holder->name }} <code class="small">{{ $holder->employee_code }}</code></td>
                        <td>{{ $holder->designation?->name }}</td><td>{{ $holder->department?->name }}</td>
                    @endif
                    <td>@if($card)<code>{{ $card->card_no }}</code> <span class="small text-body-secondary">{{ __('till') }} {{ $card->valid_until->format('d M Y') }}</span>@if($card->isExpired()) <span class="badge text-bg-warning">{{ __('Expired') }}</span>@endif @else<span class="text-body-secondary small">{{ __('not issued') }}</span>@endif</td>
                    <td class="text-end">@can('id-cards.issue')<a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" aria-label="{{ __('PDF') }}"
                        href="{{ $tab === 'students' ? route('branch.idcards.student', $holder) : route('branch.idcards.employee', $holder) }}"><i class="bi bi-file-earmark-pdf"></i></a>@endcan</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</form>
@endif
@endsection
