@extends('layouts.branch')
@section('title', __('Library'))

@section('content')
<x-page-header :title="__('Library')" :breadcrumbs="[__('Library') => null]">
    <x-slot:actions>
        @can('library.issue')<a href="{{ route('branch.library.desk') }}" class="btn btn-brand btn-sm"><i class="bi bi-upc-scan me-1"></i>{{ __('Circulation desk') }}</a>@endcan
        @can('library.create')<a href="{{ route('branch.library.books.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add book') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Titles')" :value="number_format($stats['titles'])" icon="fa-solid fa-book" :sub="number_format($stats['copies']).' '.__('copies')" :href="route('branch.library.books.index')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('On the shelf')" :value="number_format($stats['available'])" icon="fa-solid fa-check" bg="#bbf7d0" color="#15803d" :href="route('branch.library.books.index', ['availability' => 'available'])" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Out on loan')" :value="number_format($stats['out'])" icon="fa-solid fa-hand-holding" bg="#ddd6fe" color="#6d28d9" :sub="number_format($stats['reservations']).' '.__('reserved')" :href="route('branch.library.loans')" /></div>
    <div class="col-6 col-lg-3"><x-stat-card :label="__('Overdue')" :value="number_format($stats['overdue'])" icon="fa-solid fa-triangle-exclamation" bg="#fecaca" color="#b91c1c" :sub="'৳'.number_format($stats['unpaid'], 2).' '.__('fines unpaid')" :href="route('branch.library.loans', ['overdue' => 1])" /></div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-transparent fw-semibold">{{ __('Due today or overdue') }}</div>
            @if($due->isEmpty())
                <x-empty-state icon="emoji-smile" :title="__('Nothing is due')" :message="__('No book is overdue right now.')" />
            @else
                <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
                    <thead><tr><th>{{ __('Book') }}</th><th>{{ __('Member') }}</th><th>{{ __('Due') }}</th></tr></thead>
                    <tbody>@foreach($due as $loan)
                        <tr>
                            <td>{{ $loan->book?->displayTitle() }}<div class="text-body-secondary">{{ $loan->copy?->accession_no }}</div></td>
                            <td><a href="{{ route('branch.library.member', [$loan->member_type, $loan->member_id]) }}">{{ $loan->memberModel()?->name ?? '—' }}</a></td>
                            <td class="{{ $loan->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $loan->due_on->format('d M Y') }}</td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
            @endif
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-transparent fw-semibold">{{ __('Most borrowed (90 days)') }}</div>
            @if($popular->isEmpty())
                <x-empty-state icon="graph-up" :title="__('No lending yet')" />
            @else
                <ul class="list-group list-group-flush small">@foreach($popular as $row)
                    <li class="list-group-item d-flex justify-content-between"><a href="{{ route('branch.library.books.show', $row->library_book_id) }}">{{ $row->book?->displayTitle() }}</a><span class="badge text-bg-light">{{ $row->loans }}</span></li>
                @endforeach</ul>
            @endif
        </div>
    </div>
</div>
@endsection
