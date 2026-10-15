@extends('layouts.branch')
@section('title', __('Notices'))

@section('content')
<x-page-header :title="__('Notices')" :breadcrumbs="[__('Communication') => null, __('Notices') => null]">
    <x-slot:actions>
        <a href="{{ route('board.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pin-angle me-1"></i>{{ __('Notice board') }}</a>
        @can('notices.create')<a href="{{ route('branch.communication.notices.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New notice') }}</a>@endcan
    </x-slot:actions>
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                <option value="">{{ __('All statuses') }}</option>
                @foreach(['live' => __('Live'), 'draft' => __('Draft'), 'scheduled' => __('Scheduled'), 'expired' => __('Expired'), 'archived' => __('Archived')] as $k => $v)
                    <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                @endforeach
            </select></div>
            <div class="col-md-3"><select name="category" class="form-select form-select-sm" aria-label="{{ __('Category') }}">
                <option value="">{{ __('All categories') }}</option>
                @foreach(App\Models\Communication\Notice::CATEGORIES as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ __(ucfirst($c)) }}</option>@endforeach
            </select></div>
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search title') }}" aria-label="{{ __('Search title') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($notices->isEmpty())
        <x-empty-state icon="megaphone" :title="__('No notices yet')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Audience') }}</th><th>{{ __('Publish') }}</th><th>{{ __('Expires') }}</th><th class="text-end">{{ __('Read') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($notices as $n)
                    <tr>
                        <td>
                            @if($n->is_emergency)<span class="badge text-bg-danger me-1">{{ __('Emergency') }}</span>@endif
                            @if($n->is_pinned)<i class="bi bi-pin-angle-fill text-warning me-1" title="{{ __('Pinned') }}"></i>@endif
                            <a href="{{ route('branch.communication.notices.show', $n) }}" class="fw-semibold text-decoration-none">{{ $n->title }}</a>
                            <div class="text-body-secondary">{{ __(ucfirst($n->category)) }} · {{ $n->creator?->name }}@if($n->group_uuid) · <i class="bi bi-diagram-3" title="{{ __('Institution-wide') }}"></i>@endif</div>
                        </td>
                        <td>{{ __(Illuminate\Support\Str::headline($n->audience)) }}</td>
                        <td class="text-nowrap">{{ ($n->published_at ?? $n->publish_at)?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="text-nowrap">{{ $n->expires_at?->format('d M Y') ?? '—' }}</td>
                        <td class="text-end">{{ $n->reads_count }}</td>
                        <td><span class="badge text-bg-{{ $n->tone() }}">{{ __(ucfirst($n->displayStatus())) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $notices->links() }}</div>
    @endif
</div>
@endsection
