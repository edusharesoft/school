@extends('layouts.branch')
@section('title', $book->displayTitle())

@section('content')
<x-page-header :title="$book->displayTitle()" :breadcrumbs="[__('Books') => route('branch.library.books.index'), $book->displayTitle() => null]">
    <x-slot:actions>
        <a href="{{ route('branch.library.books.labels', $book) }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upc me-1"></i>{{ __('Barcode labels') }}</a>
        @can('library.update')<a href="{{ route('branch.library.books.edit', $book) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body small">
            @if($book->title_bn && $book->title_bn !== $book->displayTitle())<div class="mb-2 text-body-secondary">{{ $book->title_bn }}</div>@endif
            <dl class="row mb-0">
                <dt class="col-5">{{ __('Authors') }}</dt><dd class="col-7">{{ $book->authors ?: '—' }}</dd>
                <dt class="col-5">{{ __('Publisher') }}</dt><dd class="col-7">{{ $book->publisher ?: '—' }}{{ $book->edition ? ', '.$book->edition : '' }}</dd>
                <dt class="col-5">ISBN</dt><dd class="col-7">{{ $book->isbn ?: '—' }}</dd>
                <dt class="col-5">{{ __('Category') }}</dt><dd class="col-7">{{ $book->category?->displayName() ?? '—' }}</dd>
                <dt class="col-5">{{ __('Language') }}</dt><dd class="col-7">{{ config('library.languages.'.$book->language, $book->language) }}</dd>
                <dt class="col-5">{{ __('Shelf') }}</dt><dd class="col-7">{{ $book->shelf ?: '—' }}</dd>
                <dt class="col-5">{{ __('Price') }}</dt><dd class="col-7">৳{{ number_format((float) $book->price, 2) }}</dd>
            </dl>
            @if($book->description)<hr><p class="mb-0">{{ $book->description }}</p>@endif
        </div></div>
        @can('library.update')
        <form method="POST" action="{{ route('branch.library.books.copies.store', $book) }}" class="card mt-3">@csrf
            <div class="card-header bg-transparent fw-semibold">{{ __('Add copies') }}</div>
            <div class="card-body row g-2">
                <div class="col-6"><label class="form-label small" for="count">{{ __('How many') }}</label><input id="count" type="number" name="count" value="1" min="1" max="200" class="form-control form-control-sm" required></div>
                <div class="col-6"><label class="form-label small" for="cprice">{{ __('Price each') }}</label><input id="cprice" type="number" step="0.01" min="0" name="price" class="form-control form-control-sm"></div>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-sm btn-brand">{{ __('Add') }}</button></div>
        </form>
        @endcan
    </div>

    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-transparent fw-semibold">{{ __('Copies') }} ({{ $copies->count() }})</div>
            @if($copies->isEmpty())<x-empty-state icon="upc" :title="__('No copies yet')" :message="__('Add copies so the book can be lent.')" />
            @else
            <div class="table-responsive"><table class="table align-middle mb-0 small">
                <thead><tr><th>{{ __('Accession no.') }}</th><th>{{ __('Status') }}</th><th>{{ __('Held by') }}</th><th></th></tr></thead>
                <tbody>@foreach($copies as $copy)
                    @php($holder = $holders->get($copy->id))
                    <tr>
                        <td class="fw-semibold">{{ $copy->accession_no }}</td>
                        <td><span class="badge text-bg-{{ $copy->tone() }}">{{ __(ucfirst($copy->status)) }}</span></td>
                        <td>@if($holder)<a href="{{ route('branch.library.member', [$holder->member_type, $holder->member_id]) }}">{{ $holder->memberModel()?->name }}</a> <span class="{{ $holder->isOverdue() ? 'text-danger' : 'text-body-secondary' }}">· {{ __('due') }} {{ $holder->due_on->format('d M') }}</span>@else — @endif</td>
                        <td class="text-end">@can('library.update')@if(! in_array($copy->status, ['issued', 'reserved']))
                            <form method="POST" action="{{ route('branch.library.copies.status', $copy) }}" class="d-inline-flex gap-1">@csrf @method('PUT')
                                <select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">@foreach(['available', 'damaged', 'lost', 'withdrawn'] as $st)<option value="{{ $st }}" @selected($copy->status === $st)>{{ __(ucfirst($st)) }}</option>@endforeach</select>
                                <button class="btn btn-sm btn-outline-secondary">{{ __('Set') }}</button>
                            </form>@endif @endcan</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            @endif
        </div>

        @if($reservations->isNotEmpty())
        <div class="card mb-3"><div class="card-header bg-transparent fw-semibold">{{ __('Reservation queue') }}</div>
            <ul class="list-group list-group-flush small">@foreach($reservations as $i => $r)
                <li class="list-group-item">#{{ $i + 1 }} {{ $r->memberModel()?->name }} <span class="badge text-bg-{{ $r->status === 'ready' ? 'success' : 'secondary' }}">{{ $r->status === 'ready' ? __('Ready until :d', ['d' => $r->ready_until?->format('d M')]) : __('Waiting') }}</span></li>
            @endforeach</ul></div>
        @endif

        @can('library.issue')
        @if(! $copies->contains('status', 'available') && $copies->whereIn('status', ['issued', 'reserved'])->count())
        <form method="POST" action="{{ route('branch.library.reservations.store') }}" class="card mb-3" x-data="{ q: '', results: [], key: '', name: '',
                async search() { if (this.q.trim().length < 2) { this.results = []; return; } const r = await fetch(@js(route('branch.library.desk.members')) + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }); this.results = r.ok ? await r.json() : []; } }">
            @csrf <input type="hidden" name="library_book_id" value="{{ $book->id }}"><input type="hidden" name="member" :value="key">
            <div class="card-header bg-transparent fw-semibold">{{ __('Reserve for a member') }}</div>
            <div class="card-body">
                <input type="search" x-model="q" @input.debounce.300ms="search()" class="form-control form-control-sm" placeholder="{{ __('Search member…') }}" aria-label="{{ __('Search member') }}">
                <div class="list-group mt-2" x-show="results.length"><template x-for="m in results" :key="m.key"><button type="button" class="list-group-item list-group-item-action small" @click="key = m.key; name = m.name; results = []; q = m.name" x-text="m.name + ' · ' + (m.code || '')"></button></template></div>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-sm btn-brand" :disabled="!key">{{ __('Reserve') }}</button></div>
        </form>
        @endif
        @endcan

        <div class="card"><div class="card-header bg-transparent fw-semibold">{{ __('Recent lending') }}</div>
            @if($history->isEmpty())<div class="card-body small text-body-secondary">{{ __('Never lent yet.') }}</div>
            @else<ul class="list-group list-group-flush small">@foreach($history as $t)
                <li class="list-group-item">{{ $t->memberModel()?->name ?? '—' }} <span class="text-body-secondary">· {{ $t->copy?->accession_no }} · {{ $t->issued_on->format('d M Y') }} → {{ $t->returned_on?->format('d M Y') ?? __('out') }}</span></li>
            @endforeach</ul>@endif
        </div>
    </div>
</div>
@endsection
