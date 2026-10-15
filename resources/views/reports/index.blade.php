@extends($layout)
@section('title', __('Reports centre'))

@section('content')
<x-page-header :title="__('Reports centre')" :breadcrumbs="[__('Reports') => null]">
    <x-slot:actions><a href="{{ route($prefix.'.exports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-file-arrow-down me-1"></i>{{ __('My exports') }}</a></x-slot:actions>
</x-page-header>

@forelse($groups as $group => $reports)
    <h2 class="h6 text-uppercase text-body-secondary mt-4 mb-2">{{ $registry->groupLabel($group) }}</h2>
    <div class="row g-3">
        @foreach($reports as $r)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route($prefix.'.show', $r->key()) }}" class="card h-100 text-decoration-none text-body shadow-sm">
                    <div class="card-body">
                        <div class="fw-semibold">{{ $r->title() }}</div>
                        @if($r->description())<div class="small text-body-secondary mt-1">{{ $r->description() }}</div>@endif
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@empty
    <x-empty-state icon="bar-chart" :title="__('No reports available')" :message="__('Your role does not include any report yet.')" />
@endforelse
<p class="small text-body-secondary mt-4 mb-0">{{ __('Every report can be opened on screen and exported as Excel, CSV or PDF. Long reports are prepared in the background.') }}</p>
@endsection
