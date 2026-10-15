@extends('layouts.branch')
@section('title', __('Books'))

@section('content')
<x-page-header :title="__('Books')" :breadcrumbs="[__('Library') => route('branch.library.index'), __('Books') => null]">
    @can('library.create')
        <x-slot:actions><a href="{{ route('branch.library.books.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ __('Add book') }}</a></x-slot:actions>
    @endcan
</x-page-header>

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Title, author, ISBN or publisher') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-md-3"><select name="category_id" class="form-select form-select-sm" aria-label="{{ __('Category') }}"><option value="">{{ __('All categories') }}</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected((int) request('category_id') === $c->id)>{{ $c->displayName() }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="availability" class="form-select form-select-sm" aria-label="{{ __('Availability') }}"><option value="">{{ __('Any availability') }}</option>
                <option value="available" @selected(request('availability') === 'available')>{{ __('On the shelf') }}</option><option value="out" @selected(request('availability') === 'out')>{{ __('All copies out') }}</option></select></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($books->isEmpty())
        <x-empty-state icon="book" :title="__('No books found')" :message="__('Add titles to build the library catalogue.')" />
    @else
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Category') }}</th><th>{{ __('Shelf') }}</th><th class="text-center">{{ __('Available') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
            <tbody>@foreach($books as $b)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('branch.library.books.show', $b) }}" class="text-decoration-none">{{ $b->displayTitle() }}</a>
                        @unless($b->is_active)<span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless
                        <div class="text-body-secondary fw-normal">{{ $b->authors }}@if($b->isbn) · ISBN {{ $b->isbn }}@endif</div></td>
                    <td>{{ $b->category?->displayName() ?? '—' }}</td>
                    <td>{{ $b->shelf ?? '—' }}</td>
                    <td class="text-center"><span class="badge text-bg-{{ $b->available_count > 0 ? 'success' : ($b->copies_count ? 'warning' : 'secondary') }}">{{ $b->available_count }} / {{ $b->copies_count }}</span></td>
                    <td><x-row-actions :edit="route('branch.library.books.edit', $b)" :destroy="route('branch.library.books.destroy', $b)" :confirm="__('Delete this book and its copies?')"
                        :can-edit="auth()->user()->can('library.update')" :can-delete="auth()->user()->can('library.delete')"><a href="{{ route('branch.library.books.show', $b) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('View') }}"><i class="bi bi-eye"></i></a></x-row-actions></td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="card-footer bg-transparent">{{ $books->links() }}</div>
    @endif
</div>
@endsection
