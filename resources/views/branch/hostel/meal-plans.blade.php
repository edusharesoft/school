@extends('layouts.branch')
@section('title', __('Meal plans'))

@section('content')
<x-page-header :title="__('Meal plans')" :breadcrumbs="[__('Hostel') => route('branch.hostel.index'), __('Meal plans') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        @if($plans->isEmpty())<x-empty-state icon="egg-fried" :title="__('No meal plans yet')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Monthly fee') }}</th><th>{{ __('Description') }}</th><th>{{ __('Active') }}</th><th></th></tr></thead>
            <tbody>@foreach($plans as $p)
                <tr><form method="POST" action="{{ route('branch.hostel.meal-plans.update', $p) }}" id="mp{{ $p->id }}">@csrf @method('PUT')</form>
                    <td><input form="mp{{ $p->id }}" name="name" value="{{ $p->name }}" class="form-control form-control-sm" required maxlength="100" aria-label="{{ __('Name') }}"></td>
                    <td><input form="mp{{ $p->id }}" type="number" step="0.01" min="0" name="monthly_fee" value="{{ $p->monthly_fee }}" class="form-control form-control-sm" style="width:110px" required aria-label="{{ __('Monthly fee') }}"></td>
                    <td><input form="mp{{ $p->id }}" name="description" value="{{ $p->description }}" class="form-control form-control-sm" maxlength="255" aria-label="{{ __('Description') }}"></td>
                    <td><input form="mp{{ $p->id }}" type="hidden" name="is_active" value="0"><input form="mp{{ $p->id }}" type="checkbox" name="is_active" value="1" class="form-check-input" @checked($p->is_active) aria-label="{{ __('Active') }}"></td>
                    <td class="text-end text-nowrap">@can('hostel.update')<button form="mp{{ $p->id }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Save') }}"><i class="bi bi-check-lg"></i></button>@endcan
                        @can('hostel.delete')<form method="POST" action="{{ route('branch.hostel.meal-plans.destroy', $p) }}" class="d-inline" data-confirm="{{ __('Delete this plan?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button></form>@endcan</td></tr>
            @endforeach</tbody></table></div>@endif
    </div></div>
    @can('hostel.create')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.hostel.meal-plans.store') }}" class="card">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Add meal plan') }}</div>
        <div class="card-body row g-3"><x-form.input name="name" :label="__('Name')" required col="col-12" maxlength="100" /><x-form.input name="monthly_fee" type="number" step="0.01" min="0" :label="__('Monthly fee (৳)')" required col="col-12" /><x-form.input name="description" :label="__('Description')" col="col-12" maxlength="255" /></div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Add') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
