@extends($layout ?? 'layouts.central')
@section('title', __('Website pages'))

@section('content')
@php($rp = $routePrefix ?? 'central.website.')
<x-page-header :title="__('Website pages')" :breadcrumbs="[__('Website') => route($rp.'settings'), __('Pages') => null]">
    @can('create', App\Models\Website\Page::class)
        <x-slot:actions>
            @if(\Illuminate\Support\Facades\Route::has($rp.'pages.defaults'))
                <form method="POST" action="{{ route($rp.'pages.defaults') }}" class="d-inline">@csrf<button class="btn btn-outline-secondary btn-sm" title="{{ __('Creates About Us, Academic, Regulations, Contact and the footer pages if they are missing') }}"><i class="bi bi-magic me-1"></i>{{ __('Create standard pages') }}</button></form>
            @endif
            <a href="{{ route($rp.'pages.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New page') }}</a>
        </x-slot:actions>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search title or address') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> {{ __('Filter') }}</button></div>
        </form>
    </div>
    @if($pages->isEmpty())
        <x-empty-state icon="file-earmark-text" :title="__('No pages yet')" :message="__('Pages such as About Us, FAQ or Privacy Policy appear in the website menu and footer.')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Shown in') }}</th><th>{{ __('Order') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($pages as $p)
                <tr>
                    <td><span class="fw-semibold">{{ $p->title }}</span>@if($p->title_bn)<span class="text-body-secondary bn"> · {{ $p->title_bn }}</span>@endif
                        <div class="text-body-secondary">/page/{{ $p->slug }}</div></td>
                    <td>{{ ['menu' => __('Main menu'), 'footer_quick' => __('Footer · Quick Links'), 'footer_campus' => __('Footer · Our Campus'), 'hidden' => __('Not in a menu')][$p->placement] ?? $p->placement }}</td>
                    <td>{{ $p->sort_order }}</td>
                    <td><span class="badge text-bg-{{ $p->status === 'published' ? 'success' : 'secondary' }}">{{ $p->status === 'published' ? __('Published') : __('Draft') }}</span></td>
                    <td>
                        <x-row-actions :edit="route($rp.'pages.edit', $p)" :destroy="route($rp.'pages.destroy', $p)"
                            :can-edit="auth()->user()->can('update', $p)" :can-delete="auth()->user()->can('delete', $p)" :confirm="__('Delete this page?')">
                            @if($p->status === 'published')<a href="{{ $p->url() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('View') }}"><i class="bi bi-box-arrow-up-right"></i></a>@endif
                        </x-row-actions>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-footer bg-transparent">{{ $pages->links() }}</div>
    @endif
</div>
@endsection
