@extends($layout)
@section('title', __('Notifications'))

@section('content')
<x-page-header :title="__('Notifications')" :breadcrumbs="[__('Home') => route('home.redirect'), __('Notifications') => null]">
    <x-slot:actions>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-desktop-alerts data-default="{{ __('Allow desktop alerts') }}" data-granted="{{ __('Desktop alerts on') }}" data-denied="{{ __('Desktop alerts blocked in the browser') }}">
            <i class="bi bi-bell me-1"></i><span data-state>{{ __('Allow desktop alerts') }}</span></button>
        @if($unread)
            <form method="POST" action="{{ route('inbox.read-all') }}">@csrf<button class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all me-1"></i>{{ __('Mark all read') }}</button></form>
        @endif
    </x-slot:actions>
</x-page-header>

<ul class="nav nav-pills small mb-3">
    <li class="nav-item"><a class="nav-link {{ request('filter') !== 'unread' ? 'active' : '' }}" href="{{ route('inbox.index') }}">{{ __('All') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ request('filter') === 'unread' ? 'active' : '' }}" href="{{ route('inbox.index', ['filter' => 'unread']) }}">{{ __('Unread') }} <span class="badge text-bg-danger">{{ $unread }}</span></a></li>
</ul>

<div class="card">
    @if($messages->isEmpty())
        <x-empty-state icon="bell" :title="__('No notifications')" />
    @else
        <div class="list-group list-group-flush">
            @foreach($messages as $m)
                <a href="{{ route('inbox.open', $m) }}" class="list-group-item list-group-item-action py-3 {{ $m->read_at ? '' : 'bg-primary-subtle' }}">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="fw-semibold">@unless($m->read_at)<i class="bi bi-circle-fill text-primary me-1" style="font-size:.5rem;vertical-align:middle"></i>@endunless{{ $m->subject ?: __('Notification') }}</span>
                        <small class="text-body-secondary text-nowrap">{{ $m->created_at->diffForHumans() }}</small>
                    </div>
                    <div class="small text-body-secondary mt-1" style="white-space: pre-line">{{ Illuminate\Support\Str::limit($m->body, 400) }}</div>
                </a>
            @endforeach
        </div>
        <div class="card-body">{{ $messages->links() }}</div>
    @endif
</div>
@endsection
