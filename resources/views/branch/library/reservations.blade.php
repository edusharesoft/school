@extends('layouts.branch')
@section('title', __('Reservations'))

@section('content')
<x-page-header :title="__('Reservations')" :breadcrumbs="[__('Library') => route('branch.library.index'), __('Reservations') => null]" />
<div class="card">
    @if($reservations->isEmpty())<x-empty-state icon="bookmark" :title="__('No reservations')" :message="__('When every copy of a title is out, members can queue for it.')" />
    @else<div class="table-responsive"><table class="table align-middle mb-0 small">
        <thead><tr><th>{{ __('Book') }}</th><th>{{ __('Member') }}</th><th>{{ __('Reserved') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>@foreach($reservations as $r)
            <tr>
                <td><a href="{{ route('branch.library.books.show', $r->library_book_id) }}">{{ $r->book?->displayTitle() }}</a></td>
                <td>{{ $r->memberModel()?->name ?? '—' }}</td>
                <td>{{ $r->reserved_on->format('d M Y') }}</td>
                <td><span class="badge text-bg-{{ ['ready' => 'success', 'waiting' => 'secondary', 'fulfilled' => 'primary'][$r->status] ?? 'light text-dark' }}">{{ __(ucfirst($r->status)) }}</span>
                    @if($r->status === 'ready')<span class="text-body-secondary">{{ $r->copy?->accession_no }} · {{ __('until') }} {{ $r->ready_until?->format('d M') }}</span>@endif</td>
                <td class="text-end">@if($r->isActive() && auth()->user()->can('library.issue'))<form method="POST" action="{{ route('branch.library.reservations.cancel', $r) }}" data-confirm="{{ __('Cancel this reservation?') }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button></form>@endif</td>
            </tr>
        @endforeach</tbody>
    </table></div><div class="card-footer bg-transparent">{{ $reservations->links() }}</div>@endif
</div>
@endsection
