@extends($layout)
@section('title', __('Notice board'))

@php
    $icons = ['general' => 'megaphone', 'academic' => 'mortarboard', 'exam' => 'journal-check', 'holiday' => 'sun', 'event' => 'calendar-event', 'meeting' => 'people', 'fee' => 'cash-coin', 'emergency' => 'exclamation-octagon'];
@endphp

@section('content')
<x-page-header :title="__('Notice board')" :breadcrumbs="[__('Home') => route('home.redirect'), __('Notice board') => null]">
    @can('notices.create')
        <x-slot:actions><a href="{{ route('branch.communication.notices.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New notice') }}</a></x-slot:actions>
    @endcan
</x-page-header>

<div class="d-flex flex-wrap gap-1 mb-3 small">
    <a href="{{ route('board.index') }}" class="btn btn-sm {{ request('category') ? 'btn-outline-secondary' : 'btn-secondary' }}">{{ __('All') }}</a>
    @foreach(App\Models\Communication\Notice::CATEGORIES as $c)
        <a href="{{ route('board.index', ['category' => $c]) }}" class="btn btn-sm {{ request('category') === $c ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ __(ucfirst($c)) }}</a>
    @endforeach
</div>

@forelse($notices as $n)
    <a href="{{ route('board.show', $n) }}" class="card mb-2 text-decoration-none text-body {{ $n->is_emergency ? 'border-danger border-2' : '' }}">
        <div class="card-body d-flex gap-3">
            <div class="fs-3 {{ $n->is_emergency ? 'text-danger' : 'text-primary' }}"><i class="bi bi-{{ $icons[$n->category] ?? 'megaphone' }}"></i></div>
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <span class="fw-semibold">
                        @if($n->is_emergency)<span class="badge text-bg-danger me-1">{{ __('Emergency') }}</span>@endif
                        @if($n->is_pinned)<i class="bi bi-pin-angle-fill text-warning me-1"></i>@endif
                        {{ $n->title }}
                        @unless($read->has($n->id))<span class="badge text-bg-primary ms-1">{{ __('New') }}</span>@endunless
                    </span>
                    <small class="text-body-secondary">{{ $n->published_at?->format('d M Y') }}</small>
                </div>
                <div class="small text-body-secondary mt-1">{{ $n->excerpt(180) }}</div>
                @if($n->attachment_path)<div class="small mt-1"><i class="bi bi-paperclip"></i> {{ $n->attachment_name }}</div>@endif
            </div>
        </div>
    </a>
@empty
    <div class="card"><x-empty-state icon="pin-angle" :title="__('No notices right now')" /></div>
@endforelse
@endsection
