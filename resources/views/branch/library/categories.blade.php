@extends('layouts.branch')
@section('title', __('Book categories'))

@section('content')
<x-page-header :title="__('Book categories')" :breadcrumbs="[__('Library') => route('branch.library.index'), __('Categories') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        @if($categories->isEmpty())<x-empty-state icon="tags" :title="__('No categories yet')" :message="__('Group books as Fiction, Science, Religion, Reference …')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Name (Bangla)') }}</th><th class="text-center">{{ __('Books') }}</th><th></th></tr></thead>
            <tbody>@foreach($categories as $c)
                <tr>
                    <form method="POST" action="{{ route('branch.library.categories.update', $c) }}" id="cat{{ $c->id }}">@csrf @method('PUT')</form>
                    <td><input form="cat{{ $c->id }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required maxlength="100" aria-label="{{ __('Name') }}" @cannot('library.update') readonly @endcannot></td>
                    <td><input form="cat{{ $c->id }}" name="name_bn" value="{{ $c->name_bn }}" class="form-control form-control-sm" maxlength="100" aria-label="{{ __('Name (Bangla)') }}" @cannot('library.update') readonly @endcannot></td>
                    <td class="text-center">{{ $c->books_count }}</td>
                    <td class="text-end text-nowrap">
                        @can('library.update')<button form="cat{{ $c->id }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Save') }}"><i class="bi bi-check-lg"></i></button>@endcan
                        @can('library.delete')<form method="POST" action="{{ route('branch.library.categories.destroy', $c) }}" class="d-inline" data-confirm="{{ __('Delete this category?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>@endcan
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>@endif
    </div></div>
    @can('library.create')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.library.categories.store') }}" class="card">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Add category') }}</div>
        <div class="card-body row g-3"><x-form.input name="name" :label="__('Name')" required col="col-12" maxlength="100" /><x-form.input name="name_bn" :label="__('Name (Bangla)')" col="col-12" maxlength="100" /></div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Add') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
