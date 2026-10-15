@extends('layouts.portal')
@section('title', __('Library'))

@section('content')
<x-page-header :title="__('Library')" :breadcrumbs="$mode === 'parent' ? [__('My children') => route('portal.parent'), $student->name => null] : [__('Library') => null]" />

@if($account['unpaid'] > 0)
    <div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Unpaid library fines') }}: <strong>৳{{ bn_digits(number_format($account['unpaid'], 2)) }}</strong> — {{ __('please pay at the library desk.') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="dp blue mb-3">
            <div class="dp-head"><h2><i class="fa-solid fa-book-bookmark me-1"></i> {{ __('Books I have') }}</h2><span class="sub">{{ bn_digits($account['open']->count()) }} / {{ bn_digits($account['limits']['max_books']) }}</span></div>
            <div class="dp-body p-0">
                @if($account['open']->isEmpty())<p class="text-body-secondary p-3 mb-0">{{ __('No books out at the moment.') }}</p>
                @else<ul class="list-group list-group-flush small">@foreach($account['open'] as $loan)
                    <li class="list-group-item">
                        <div class="fw-semibold">{{ $loan->book?->displayTitle() }}</div>
                        <div class="{{ $loan->isOverdue() ? 'text-danger fw-semibold' : 'text-body-secondary' }}">{{ __('Due') }} {{ $loan->due_on->format('d M Y') }}@if($loan->isOverdue()) — {{ __('overdue, fine so far ৳:a', ['a' => bn_digits(number_format($library->accruedFine($loan), 2))]) }}@endif</div>
                    </li>
                @endforeach</ul>@endif
            </div>
        </div>

        @if($account['history']->isNotEmpty())
        <div class="dp blue"><div class="dp-head"><h2><i class="fa-solid fa-clock-rotate-left me-1"></i> {{ __('Borrowing history') }}</h2></div>
            <div class="dp-body p-0"><ul class="list-group list-group-flush small">@foreach($account['history']->take(10) as $loan)
                <li class="list-group-item">{{ $loan->book?->displayTitle() }} <span class="text-body-secondary">— {{ $loan->issued_on->format('d M Y') }} → {{ $loan->returned_on?->format('d M Y') }}</span></li>
            @endforeach</ul></div></div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="dp blue mb-3">
            <div class="dp-head"><h2><i class="fa-solid fa-bookmark me-1"></i> {{ __('My reservations') }}</h2></div>
            <div class="dp-body p-0">
                @if($account['reservations']->isEmpty())<p class="text-body-secondary p-3 mb-0">{{ __('No reservations.') }}</p>
                @else<ul class="list-group list-group-flush small">@foreach($account['reservations'] as $r)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $r->book?->displayTitle() }}<br><span class="badge text-bg-{{ $r->status === 'ready' ? 'success' : 'secondary' }}">{{ $r->status === 'ready' ? __('Ready — collect by :d', ['d' => $r->ready_until?->format('d M')]) : __('Waiting in the queue') }}</span></span>
                        @if($mode === 'student')<form method="POST" action="{{ route('portal.student.library.cancel', $r) }}" data-confirm="{{ __('Cancel this reservation?') }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button></form>@endif
                    </li>
                @endforeach</ul>@endif
            </div>
        </div>

        @if($mode === 'student')
        <div class="dp blue">
            <div class="dp-head"><h2><i class="fa-solid fa-magnifying-glass me-1"></i> {{ __('Find a book') }}</h2></div>
            <div class="dp-body">
                <form method="GET" class="d-flex gap-2 mb-2"><input type="search" name="q" value="{{ $term }}" class="form-control form-control-sm" placeholder="{{ __('Title, author or ISBN') }}" aria-label="{{ __('Search') }}"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button></form>
                @if(filled($term) && $books->isEmpty())<p class="small text-body-secondary mb-0">{{ __('No book found.') }}</p>@endif
                @foreach($books as $b)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2 small">
                        <div>{{ $b->displayTitle() }}<div class="text-body-secondary">{{ $b->authors }}@if($b->shelf) · {{ __('Shelf') }} {{ $b->shelf }}@endif</div></div>
                        @if($b->available_count > 0)<span class="badge text-bg-success">{{ __('On the shelf') }}</span>
                        @elseif($b->lendable_count > 0)<form method="POST" action="{{ route('portal.student.library.reserve', $b) }}">@csrf<button class="btn btn-sm btn-outline-primary">{{ __('Reserve') }}</button></form>
                        @else<span class="badge text-bg-secondary">{{ __('Unavailable') }}</span>@endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
