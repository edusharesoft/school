@extends($layout ?? 'layouts.central')
@section('title', __('News & notices'))

@section('content')
@php($rp = $routePrefix ?? 'central.website.')
<x-page-header :title="__('News & notices')" :breadcrumbs="[__('Website') => route($rp.'settings'), __('News & notices') => null]">
    @can('create', App\Models\Website\Post::class)
        <x-slot:actions>
            <a href="{{ route($rp.'posts.create', ['type' => 'notice']) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New notice') }}</a>
            <a href="{{ route($rp.'posts.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('New article') }}</a>
        </x-slot:actions>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2">
            <div class="col-md-5"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search title') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-3"><select name="type" class="form-select form-select-sm" aria-label="{{ __('Type') }}">
                <option value="">{{ __('News and notices') }}</option>
                <option value="news" @selected(request('type') === 'news')>{{ __('News & Blog') }}</option>
                <option value="notice" @selected(request('type') === 'notice')>{{ __('Notices') }}</option>
            </select></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> {{ __('Filter') }}</button></div>
        </form>
    </div>
    @if($posts->isEmpty())
        <x-empty-state icon="newspaper" :title="__('Nothing published yet')" :message="__('The three newest articles appear in “Latest News & Blog” on the home page.')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Publish date') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($posts as $p)
                <tr>
                    <td><span class="fw-semibold">{{ $p->title }}</span><div class="text-body-secondary">/{{ $p->type === 'notice' ? 'notices' : 'news' }}/{{ $p->slug }}</div></td>
                    <td>{{ $p->type === 'notice' ? __('Notice') : __('Article') }}</td>
                    <td>{{ $p->published_at?->format('d M Y H:i') }}@if($p->published_at?->isFuture()) <span class="badge text-bg-info">{{ __('Scheduled') }}</span>@endif</td>
                    <td><span class="badge text-bg-{{ $p->status === 'published' ? 'success' : 'secondary' }}">{{ $p->status === 'published' ? __('Published') : __('Draft') }}</span></td>
                    <td><x-row-actions :edit="route($rp.'posts.edit', $p)" :destroy="route($rp.'posts.destroy', $p)"
                        :can-edit="auth()->user()->can('update', $p)" :can-delete="auth()->user()->can('delete', $p)" :confirm="__('Delete this post?')" /></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="card-footer bg-transparent">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
