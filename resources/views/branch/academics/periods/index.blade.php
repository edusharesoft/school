@extends('layouts.branch')
@section('title', __('Periods & bell schedule'))

@section('content')
<x-page-header :title="__('Periods & bell schedule')" :breadcrumbs="[__('Academics') => null, __('Periods') => null]">
    <x-slot:actions>
        @can('timetables.view')
            <a href="{{ route('branch.academics.timetable.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar3-week me-1"></i>{{ __('Class routine') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            @if($periods->isEmpty())
                <x-empty-state icon="clock" :title="__('No periods yet')" :message="__('Add the bell schedule of each shift, including breaks, to start building class routines.')" />
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>#</th><th>{{ __('Shift') }}</th><th>{{ __('Name') }}</th><th>{{ __('Time') }}</th><th>{{ __('Type') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($periods as $period)
                            <tr @class(['table-light' => $period->is_break])>
                                <td class="text-body-secondary">{{ $period->sort_order }}</td>
                                <td>{{ __(ucfirst($period->shift)) }}</td>
                                <td class="fw-semibold">{{ $period->name }}</td>
                                <td class="text-nowrap">{{ $period->timeRange() }}</td>
                                <td>@if($period->is_break)<span class="badge text-bg-warning">{{ __('Break') }}</span>@else<span class="badge text-bg-light border">{{ __('Class') }}</span>@endif</td>
                                <td><x-row-actions :edit="route('branch.academics.periods.index', ['edit' => $period->id])" :destroy="route('branch.academics.periods.destroy', $period)"
                                        :confirm="__('Delete this period? Routine entries in it are removed as well.')"
                                        :can-edit="auth()->user()->can('timetables.update')" :can-delete="auth()->user()->can('timetables.delete')" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        @if($editing ? auth()->user()->can('timetables.update') : auth()->user()->can('timetables.create'))
            <form method="POST" action="{{ $editing ? route('branch.academics.periods.update', $editing) : route('branch.academics.periods.store') }}" class="card">
                @csrf
                @if($editing) @method('PUT') @endif
                <div class="card-header bg-transparent fw-semibold">{{ $editing ? __('Edit period') : __('Add period') }}</div>
                <div class="card-body row g-3">
                    <x-form.input name="name" :label="__('Name')" :value="$editing?->name" required col="col-12" maxlength="40" :placeholder="__('e.g. 1st period')" />
                    <x-form.select name="shift" :label="__('Shift')" :value="$editing?->shift ?? 'day'" required col="col-12"
                        :options="collect(App\Models\Academic\Period::SHIFTS)->mapWithKeys(fn ($s) => [$s => __(ucfirst($s))])->all()" />
                    <x-form.input name="starts_at" type="time" :label="__('Starts at')" :value="$editing ? substr($editing->starts_at, 0, 5) : null" required />
                    <x-form.input name="ends_at" type="time" :label="__('Ends at')" :value="$editing ? substr($editing->ends_at, 0, 5) : null" required />
                    <x-form.input name="sort_order" type="number" :label="__('Order')" :value="$editing?->sort_order ?? ($periods->max('sort_order') + 1)" min="0" max="99" col="col-12" />
                    <x-form.switch name="is_break" :label="__('This is a break (no class)')" :checked="$editing?->is_break" />
                </div>
                <x-form.actions :cancel="route('branch.academics.periods.index')" />
            </form>
        @endif
    </div>
</div>
@endsection
