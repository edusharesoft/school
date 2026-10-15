@extends('layouts.branch')
@section('title', __('Hostel visitors'))

@section('content')
<x-page-header :title="__('Hostel visitors')" :breadcrumbs="[__('Hostel') => route('branch.hostel.index'), __('Visitors') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        <div class="card-body border-bottom"><form method="GET" class="d-flex gap-2"><select name="inside" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()" aria-label="{{ __('Filter') }}"><option value="">{{ __('All visits') }}</option><option value="1" @selected(request('inside'))>{{ __('Still inside') }}</option></select></form></div>
        @if($visitors->isEmpty())<x-empty-state icon="people" :title="__('No visitors')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Visitor') }}</th><th>{{ __('Visiting') }}</th><th>{{ __('In') }}</th><th>{{ __('Out') }}</th></tr></thead>
            <tbody>@foreach($visitors as $v)
                <tr><td>{{ $v->visitor_name }}<div class="text-body-secondary">{{ $v->relation }} {{ $v->phone }}</div></td><td>{{ $v->student?->name }}<div class="text-body-secondary">{{ $v->hostel?->name }}</div></td><td>{{ $v->in_at->format('d M, H:i') }}</td>
                    <td>@if($v->out_at){{ $v->out_at->format('d M, H:i') }}@elseif(auth()->user()->can('hostel.update'))<form method="POST" action="{{ route('branch.hostel.visitors.checkout', $v) }}">@csrf<button class="btn btn-sm btn-outline-primary">{{ __('Check out') }}</button></form>@else <span class="badge text-bg-warning">{{ __('Inside') }}</span>@endif</td></tr>
            @endforeach</tbody></table></div><div class="card-footer bg-transparent">{{ $visitors->links() }}</div>@endif
    </div></div>
    @can('hostel.create')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.hostel.visitors.store') }}" class="card">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Check in a visitor') }}</div>
        <div class="card-body row g-3">
            <div class="col-12"><label class="form-label" for="allocation_id">{{ __('Resident') }} <span class="text-danger">*</span></label><select id="allocation_id" name="allocation_id" class="form-select @error('allocation_id') is-invalid @enderror" required><option value="">{{ __('Choose…') }}</option>@foreach($residents as $a)<option value="{{ $a->id }}">{{ $a->student?->name }} — {{ $a->hostel?->name }}</option>@endforeach</select>@error('allocation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <x-form.input name="visitor_name" :label="__('Visitor name')" required col="col-12" maxlength="120" />
            <x-form.input name="relation" :label="__('Relation')" col="col-6" maxlength="60" /><x-form.input name="phone" :label="__('Phone')" col="col-6" maxlength="30" />
            <x-form.input name="purpose" :label="__('Purpose')" col="col-12" maxlength="255" />
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Check in') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
