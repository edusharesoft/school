@extends($layout)
@section('title', __('Apply for leave'))

@section('content')
<x-page-header :title="__('Apply for leave')" :breadcrumbs="[__('Leave applications') => route($routePrefix.'leaves.index'), __('New') => null]" />

<div class="row g-4">
    <div class="col-lg-7">
        <form method="POST" action="{{ route($routePrefix.'leaves.store') }}" class="card" x-data="{ half: {{ old('is_half_day') ? 'true' : 'false' }} }">
            @csrf
            <div class="card-body row g-3">
                @if($employees->isNotEmpty())
                    <div class="col-12"><label class="form-label" for="employee_id">{{ __('Employee') }}</label>
                        <select id="employee_id" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror">
                            @if($own)<option value="">{{ __('Myself') }} ({{ $own->name }})</option>@else<option value="">—</option>@endif
                            @foreach($employees as $e)
                                @if(! $own || $e->id !== $own->id)<option value="{{ $e->id }}" @selected((int) old('employee_id') === $e->id)>{{ $e->name }} ({{ $e->employee_code }})</option>@endif
                            @endforeach
                        </select>
                        @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('HR desk: apply on behalf of an employee.') }}</div></div>
                @endif
                <x-form.select name="leave_type_id" :label="__('Leave type')" required col="col-md-6" placeholder="—"
                    :options="$types->mapWithKeys(fn ($t) => [$t->id => $t->name.($t->isUnlimited() ? '' : ' ('.$t->days_per_year.' '.__('days/year').')')])->all()" />
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_half_day" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_half_day" name="is_half_day" value="1" x-model="half">
                        <label class="form-check-label" for="is_half_day">{{ __('Half day') }}</label>
                    </div>
                </div>
                <x-form.input name="from_date" type="date" :label="__('From')" :value="now()->toDateString()" required col="col-md-6" />
                <div class="col-md-6" x-show="!half">
                    <label class="form-label" for="to_date">{{ __('To') }}</label>
                    <input id="to_date" type="date" name="to_date" value="{{ old('to_date', now()->toDateString()) }}" class="form-control @error('to_date') is-invalid @enderror">
                    @error('to_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12"><label class="form-label" for="reason">{{ __('Reason') }} <span class="text-danger">*</span></label>
                    <textarea id="reason" name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" required minlength="5" maxlength="1000">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 small text-body-secondary">{{ __('Weekly holidays inside the range are not counted. Approved leave is written into your attendance automatically.') }}</div>
            </div>
            <x-form.actions :cancel="route($routePrefix.'leaves.index')">{{ __('Submit application') }}</x-form.actions>
        </form>
    </div>
    @if($balances->isNotEmpty())
        <div class="col-lg-5">
            <div class="dp green">
                <div class="dp-head"><h2><i class="fa-solid fa-scale-balanced me-1"></i> {{ __('My leave balance :year', ['year' => bn_digits(now()->year)]) }}</h2></div>
                <div class="dp-body p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Taken') }}</th><th>{{ __('Pending') }}</th><th>{{ __('Left') }}</th></tr></thead>
                        <tbody>@foreach($balances as $b)<tr><td>{{ $b['type']->name }}</td><td>{{ bn_digits($b['used']) }}</td><td>{{ bn_digits($b['pending']) }}</td><td class="fw-semibold">{{ $b['remaining'] === null ? '∞' : bn_digits($b['remaining']) }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
