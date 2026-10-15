@extends($layout)
@section('title', __('Online & offline classes'))

@section('content')
<x-page-header :title="__('Online & offline classes')" :breadcrumbs="[__('Learning') => null, __('Online & offline classes') => null]">
    @can('online-classes.create')
        <x-slot:actions><a href="{{ route($routePrefix.'learning.online-classes.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Schedule a class') }}</a></x-slot:actions>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><select name="when" class="form-select form-select-sm" aria-label="{{ __('Show') }}">
                @foreach(['upcoming' => __('Upcoming'), 'past' => __('Past, completed and cancelled'), 'all' => __('All')] as $k => $label)<option value="{{ $k }}" @selected($when === $k)>{{ $label }}</option>@endforeach
            </select></div>
            <div class="col-md-3"><select name="mode" class="form-select form-select-sm" aria-label="{{ __('Mode') }}">
                <option value="">{{ __('Online and offline') }}</option>
                <option value="online" @selected(request('mode') === 'online')>{{ __('Online') }}</option>
                <option value="offline" @selected(request('mode') === 'offline')>{{ __('Offline') }}</option>
            </select></div>
            <div class="col-md-3"><select name="class_id" class="form-select form-select-sm" aria-label="{{ __('Class') }}">
                <option value="">{{ __('All classes') }}</option>
                @foreach($classes as $id => $name)<option value="{{ $id }}" @selected((int) request('class_id') === $id)>{{ $name }}</option>@endforeach
            </select></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($items->isEmpty())
        <x-empty-state icon="camera-video" :title="__('No classes scheduled')" :message="__('Schedule an online class with Zoom or Google Meet, or an offline class in a room.')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Class') }}</th><th>{{ __('When') }}</th><th>{{ __('Where') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('State') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($items as $c)
                <tr>
                    <td class="fw-semibold"><a href="{{ route($routePrefix.'learning.online-classes.show', $c) }}" class="text-decoration-none">{{ $c->title }}</a>
                        <div class="text-body-secondary fw-normal">{{ $c->schoolClass?->name }}@if($c->section) — {{ $c->section->name }}@endif · {{ $c->subject?->displayName() ?? '—' }}</div></td>
                    <td>{{ $c->starts_at->format('D, d M Y') }}<div class="text-body-secondary">{{ $c->starts_at->format('H:i') }} – {{ $c->ends_at->format('H:i') }}</div></td>
                    <td>@if($c->isOnline())<i class="bi bi-camera-video me-1"></i>{{ $c->providerLabel() }}@if($c->sync_status === 'failed') <span class="badge text-bg-danger" title="{{ $c->sync_error }}">{{ __('Sync failed') }}</span>@endif
                        @else<i class="bi bi-building me-1"></i>{{ $c->room ?: __('In school') }}@endif</td>
                    <td>{{ $c->teacher?->name ?? '—' }}</td>
                    <td><span class="badge text-bg-{{ $c->tone() }}">{{ __(Illuminate\Support\Str::headline($c->phase())) }}</span></td>
                    <td class="text-end"><a href="{{ route($routePrefix.'learning.online-classes.show', $c) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Open') }}"><i class="bi bi-eye"></i></a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-footer bg-transparent">{{ $items->links() }}</div>
    @endif
</div>
@endsection
