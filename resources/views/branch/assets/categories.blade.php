@extends('layouts.branch')
@section('title', __('Asset categories'))

@section('content')
<x-page-header :title="__('Asset categories')" :breadcrumbs="[__('Assets') => route('branch.assets.index'), __('Asset register') => route('branch.assets.register.index'), __('Categories') => null]" />
<div class="alert alert-light border small">{{ __('The depreciation settings of a category are offered when an asset of that category is registered; each asset keeps its own copy, so changing them here does not alter existing assets.') }}</div>
<div class="row g-3">
    <div class="col-xl-8"><div class="card">
        @if($categories->isEmpty())<x-empty-state icon="tags" :title="__('No categories yet')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Method') }}</th><th>{{ __('Life (months)') }}</th><th>{{ __('Salvage %') }}</th><th>{{ __('Rate %/yr') }}</th><th class="text-end">{{ __('Assets') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>@foreach($categories as $c)
                <tr><form method="POST" action="{{ route('branch.assets.categories.update', $c) }}" id="cat{{ $c->id }}">@csrf @method('PUT')</form>
                    <td><input form="cat{{ $c->id }}" name="name" value="{{ $c->name }}" class="form-control form-control-sm" required maxlength="100" aria-label="{{ __('Name') }}"></td>
                    <td><select form="cat{{ $c->id }}" name="kind" class="form-select form-select-sm" aria-label="{{ __('Kind') }}">@foreach(App\Models\Assets\Category::KINDS as $k)<option value="{{ $k }}" @selected($c->kind === $k)>{{ __(ucfirst($k)) }}</option>@endforeach</select></td>
                    <td><select form="cat{{ $c->id }}" name="depreciation_method" class="form-select form-select-sm" aria-label="{{ __('Method') }}">@foreach(App\Models\Assets\Category::METHODS as $m)<option value="{{ $m }}" @selected($c->depreciation_method === $m)>{{ __(ucwords(str_replace('_', ' ', $m))) }}</option>@endforeach</select></td>
                    <td><input form="cat{{ $c->id }}" type="number" name="useful_life_months" value="{{ $c->useful_life_months }}" min="0" max="1200" class="form-control form-control-sm" style="width:90px" aria-label="{{ __('Life (months)') }}"></td>
                    <td><input form="cat{{ $c->id }}" type="number" step="0.01" name="salvage_percent" value="{{ $c->salvage_percent }}" min="0" max="100" class="form-control form-control-sm" style="width:80px" aria-label="{{ __('Salvage %') }}"></td>
                    <td><input form="cat{{ $c->id }}" type="number" step="0.01" name="declining_rate" value="{{ $c->declining_rate }}" min="0" max="100" class="form-control form-control-sm" style="width:80px" aria-label="{{ __('Rate %/yr') }}"></td>
                    <td class="text-end">{{ $c->assets_count }}</td>
                    <td><input form="cat{{ $c->id }}" type="hidden" name="is_active" value="0"><input form="cat{{ $c->id }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($c->is_active) aria-label="{{ __('Active') }}"></td>
                    <td class="text-end text-nowrap">@can('assets.update')<button form="cat{{ $c->id }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Save') }}"><i class="bi bi-check-lg"></i></button>@endcan
                        @can('assets.delete')<form method="POST" action="{{ route('branch.assets.categories.destroy', $c) }}" class="d-inline" data-confirm="{{ __('Delete this category?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>@endcan</td></tr>
            @endforeach</tbody></table></div>@endif
    </div></div>
    @can('assets.create')
    <div class="col-xl-4"><form method="POST" action="{{ route('branch.assets.categories.store') }}" class="card">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Add category') }}</div>
        <div class="card-body row g-3">
            <x-form.input name="name" :label="__('Name')" required col="col-12" maxlength="100" />
            <x-form.select name="kind" :label="__('Kind')" col="col-md-6" :options="['general' => __('General'), 'laboratory' => __('Laboratory')]" :value="'general'" />
            <x-form.select name="depreciation_method" :label="__('Depreciation')" col="col-md-6" :value="'straight_line'" :options="['straight_line' => __('Straight line'), 'declining_balance' => __('Declining balance'), 'none' => __('None (land, art …)')]" />
            <x-form.input name="useful_life_months" type="number" min="0" max="1200" :label="__('Useful life (months)')" :value="60" col="col-md-4" />
            <x-form.input name="salvage_percent" type="number" step="0.01" min="0" max="100" :label="__('Salvage value %')" :value="0" col="col-md-4" />
            <x-form.input name="declining_rate" type="number" step="0.01" min="0" max="100" :label="__('Yearly rate %')" :value="0" col="col-md-4" :help="__('declining balance only')" />
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Add') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
