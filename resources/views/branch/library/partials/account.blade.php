{{-- A member's library account: loans out, reservations, fines. Needs $member, $type, $account; $returnable shows renew/return buttons. --}}
@php($returnable = $returnable ?? false)
<div class="card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <div><span class="fw-semibold">{{ $member->name }}</span> <span class="text-body-secondary small">{{ \App\Services\Library\LibraryMembers::code($member) }} · {{ __(ucfirst($type)) }}</span></div>
        <span class="small text-body-secondary">{{ $account['open']->count() }} / {{ $account['limits']['max_books'] }} {{ __('books out') }}</span>
    </div>
    @if($account['unpaid'] > 0)
        <div class="alert alert-warning rounded-0 mb-0 py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Unpaid fines') }}: ৳{{ number_format($account['unpaid'], 2) }} — <a href="{{ route('branch.library.fines.index') }}">{{ __('Collect') }}</a></div>
    @endif
    <div class="card-body pb-0 fw-semibold small">{{ __('Books out') }}</div>
    @if($account['open']->isEmpty())
        <div class="px-3 pb-3 small text-body-secondary">{{ __('No books out.') }}</div>
    @else
        <ul class="list-group list-group-flush small">@foreach($account['open'] as $loan)
            <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                <div>{{ $loan->book?->displayTitle() }} <span class="text-body-secondary">({{ $loan->copy?->accession_no }})</span>
                    <div class="{{ $loan->isOverdue() ? 'text-danger fw-semibold' : 'text-body-secondary' }}">{{ __('Due') }} {{ $loan->due_on->format('d M Y') }}@if($loan->isOverdue()) — {{ __('overdue, fine so far ৳:a', ['a' => number_format($library->accruedFine($loan), 2)]) }}@endif · {{ __('renewed :n×', ['n' => $loan->renewals]) }}</div></div>
                @if($returnable)
                    <div class="text-nowrap">
                        <form method="POST" action="{{ route('branch.library.renew', $loan) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">{{ __('Renew') }}</button></form>
                        <form method="POST" action="{{ route('branch.library.return') }}" class="d-inline">@csrf<input type="hidden" name="loan_id" value="{{ $loan->id }}"><input type="hidden" name="condition" value="good"><button class="btn btn-sm btn-outline-primary">{{ __('Return') }}</button></form>
                    </div>
                @endif
            </li>
        @endforeach</ul>
    @endif
    @if($account['reservations']->isNotEmpty())
        <div class="card-body pb-0 fw-semibold small">{{ __('Reservations') }}</div>
        <ul class="list-group list-group-flush small">@foreach($account['reservations'] as $r)
            <li class="list-group-item">{{ $r->book?->displayTitle() }} <span class="badge text-bg-{{ $r->status === 'ready' ? 'success' : 'secondary' }}">{{ $r->status === 'ready' ? __('Ready until :d', ['d' => $r->ready_until?->format('d M')]) : __('Waiting') }}</span></li>
        @endforeach</ul>
    @endif
    @if($account['history']->isNotEmpty())
        <div class="card-body pb-0 fw-semibold small">{{ __('Recent history') }}</div>
        <ul class="list-group list-group-flush small">@foreach($account['history']->take(6) as $loan)
            <li class="list-group-item">{{ $loan->book?->displayTitle() }} <span class="text-body-secondary">— {{ $loan->issued_on->format('d M') }} → {{ $loan->returned_on?->format('d M Y') }}</span>@if($loan->status === 'lost') <span class="badge text-bg-danger">{{ __('Lost') }}</span>@endif</li>
        @endforeach</ul>
    @endif
</div>
