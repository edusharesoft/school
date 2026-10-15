@extends('layouts.branch')
@section('title', __('Hostel residents'))

@section('content')
<x-page-header :title="__('Hostel residents')" :breadcrumbs="[__('Hostel') => route('branch.hostel.index'), __('Residents') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        <div class="card-body border-bottom"><form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="active">{{ __('Living now') }}</option><option value="ended" @selected($status === 'ended')>{{ __('Left / moved') }}</option></select></div>
            <div class="col-md-3"><select name="hostel_id" class="form-select form-select-sm" aria-label="{{ __('Hostel') }}"><option value="">{{ __('All hostels') }}</option>@foreach($hostels as $id => $name)<option value="{{ $id }}" @selected((int) request('hostel_id') === $id)>{{ $name }}</option>@endforeach</select></div>
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Student') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form></div>
        @if($allocations->isEmpty())<x-empty-state icon="person-check" :title="__('No residents here')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Student') }}</th><th>{{ __('Hostel / bed') }}</th><th>{{ __('Meals') }}</th><th class="text-end">{{ __('Per month') }}</th><th></th></tr></thead>
            <tbody>@foreach($allocations as $a)
                <tr><td>{{ $a->student?->name }}<div class="text-body-secondary">{{ $a->student?->student_code }} · {{ $a->starts_on->format('d M Y') }}@if($a->ends_on) → {{ $a->ends_on->format('d M Y') }}@endif</div></td>
                    <td>{{ $a->hostel?->name }}<div class="text-body-secondary">{{ __('Room') }} {{ $a->room?->room_no }} · {{ $a->bed?->label }}</div></td>
                    <td>@if($a->status === 'active' && auth()->user()->can('hostel.update'))
                        <form method="POST" action="{{ route('branch.hostel.allocations.meal-plan', $a) }}" class="d-inline-flex gap-1">@csrf<select name="hostel_meal_plan_id" class="form-select form-select-sm" style="width:140px" aria-label="{{ __('Meal plan') }}"><option value="">—</option>@foreach($plans as $p)<option value="{{ $p->id }}" @selected($a->hostel_meal_plan_id === $p->id)>{{ $p->name }}</option>@endforeach</select><button class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Save') }}"><i class="bi bi-check-lg"></i></button></form>
                        @else{{ $a->mealPlan?->name ?? '—' }}@endif</td>
                    <td class="text-end">৳{{ number_format($a->monthlyTotal(), 0) }}</td>
                    <td class="text-end text-nowrap">@if($a->status === 'active' && auth()->user()->can('hostel.update'))
                        <form method="POST" action="{{ route('branch.hostel.allocations.transfer', $a) }}" class="d-inline-flex gap-1">@csrf<select name="hostel_bed_id" class="form-select form-select-sm" style="width:150px" aria-label="{{ __('New bed') }}">@foreach($freeBeds as $b)<option value="{{ $b['id'] }}">{{ $b['label'] }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary" @disabled($freeBeds->isEmpty())>{{ __('Move') }}</button></form>
                        <form method="POST" action="{{ route('branch.hostel.allocations.vacate', $a) }}" class="d-inline" data-confirm="{{ __('Vacate this bed today?') }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Vacate') }}</button></form>
                    @elseif($a->end_reason){{ $a->end_reason }}@endif</td></tr>
            @endforeach</tbody></table></div><div class="card-footer bg-transparent">{{ $allocations->links() }}</div>@endif
    </div></div>
    @can('hostel.create')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.hostel.allocations.store') }}" class="card">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Allocate a bed') }}</div>
        <div class="card-body row g-3">
            <x-student-picker col="col-12" />
            <div class="col-12"><label class="form-label" for="hostel_bed_id">{{ __('Free bed') }} <span class="text-danger">*</span></label>
                <select id="hostel_bed_id" name="hostel_bed_id" class="form-select @error('hostel_bed_id') is-invalid @enderror" required><option value="">{{ __('Choose…') }}</option>@foreach($freeBeds as $b)<option value="{{ $b['id'] }}">{{ $b['label'] }} — {{ __(ucfirst($b['type'])) }}</option>@endforeach</select>@error('hostel_bed_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <x-form.select name="hostel_meal_plan_id" :label="__('Meal plan')" :options="$plans->mapWithKeys(fn ($p) => [$p->id => $p->name.' (৳'.number_format((float) $p->monthly_fee, 0).')'])->all()" :placeholder="__('No meals')" col="col-12" />
            <x-form.input name="starts_on" type="date" :label="__('Starts on')" :value="now()->toDateString()" col="col-12" />
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Allocate') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
