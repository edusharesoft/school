@extends('layouts.branch')
@section('title', __('Item categories'))

@section('content')
<x-page-header :title="__('Item categories')" :breadcrumbs="[__('Inventory') => route('branch.inventory.index'), __('Items & stock') => route('branch.inventory.items.index'), __('Categories') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        @if($categories->isEmpty())<x-empty-state icon="tags" :title="__('No categories yet')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Charged to on issue') }}</th><th class="text-end">{{ __('Items') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>@foreach($categories as $c)
                <tr><form method="POST" action="{{ route('branch.inventory.categories.update', $c) }}" id="cat{{ $c->id }}">@csrf @method('PUT')</form>
                    <td><input form="cat{{ $c->id }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required maxlength="100" aria-label="{{ __('Name') }}"></td>
                    <td><select form="cat{{ $c->id }}" name="kind" class="form-select form-select-sm" aria-label="{{ __('Kind') }}">@foreach(App\Models\Inventory\Category::KINDS as $k)<option value="{{ $k }}" @selected($c->kind === $k)>{{ __(ucfirst($k)) }}</option>@endforeach</select></td>
                    <td><select form="cat{{ $c->id }}" name="expense_account_id" class="form-select form-select-sm" aria-label="{{ __('Expense account') }}"><option value="">{{ __('Consumables & supplies used (default)') }}</option>@foreach($accounts as $id => $name)<option value="{{ $id }}" @selected($c->expense_account_id === $id)>{{ $name }}</option>@endforeach</select></td>
                    <td class="text-end">{{ $c->items_count }}</td>
                    <td><input form="cat{{ $c->id }}" type="hidden" name="is_active" value="0"><input form="cat{{ $c->id }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($c->is_active) aria-label="{{ __('Active') }}"></td>
                    <td class="text-end text-nowrap">@can('inventory.update')<button form="cat{{ $c->id }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Save') }}"><i class="bi bi-check-lg"></i></button>@endcan
                        @can('inventory.delete')<form method="POST" action="{{ route('branch.inventory.categories.destroy', $c) }}" class="d-inline" data-confirm="{{ __('Delete this category?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>@endcan</td></tr>
            @endforeach</tbody></table></div>@endif
    </div></div>
    @can('inventory.create')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.inventory.categories.store') }}" class="card">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Add category') }}</div>
        <div class="card-body row g-3">
            <x-form.input name="name" :label="__('Name')" required col="col-12" maxlength="100" />
            <x-form.select name="kind" :label="__('Kind')" col="col-12" :options="['general' => __('General'), 'laboratory' => __('Laboratory')]" :value="'general'" />
            <x-form.select name="expense_account_id" :label="__('Charged to on issue')" col="col-12" :options="$accounts" :placeholder="__('Consumables & supplies used (default)')" />
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Add') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
