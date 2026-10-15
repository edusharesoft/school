@extends('layouts.branch')
@section('title', __('Hostel maintenance'))

@section('content')
<x-page-header :title="__('Hostel maintenance')" :breadcrumbs="[__('Hostel') => route('branch.hostel.index'), __('Maintenance') => null]" />
<div class="row g-3">
    <div class="col-lg-8"><div class="card">
        <div class="card-body border-bottom"><form method="GET" class="d-flex gap-2"><select name="status" class="form-select form-select-sm" style="max-width:200px" onchange="this.form.submit()" aria-label="{{ __('Filter') }}"><option value="">{{ __('Open requests') }}</option><option value="all" @selected(request('status') === 'all')>{{ __('All requests') }}</option></select></form></div>
        @if($requests->isEmpty())<x-empty-state icon="tools" :title="__('No requests')" />
        @else<div class="table-responsive"><table class="table align-middle mb-0 small">
            <thead><tr><th>{{ __('Request') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>@foreach($requests as $r)
                <tr><td class="fw-semibold">{{ $r->title }}<div class="text-body-secondary fw-normal">{{ $r->hostel?->name }}{{ $r->room ? ' · '.__('Room').' '.$r->room->room_no : '' }} · {{ $r->created_at->format('d M') }}</div>@if($r->description)<div class="fw-normal">{{ $r->description }}</div>@endif</td>
                    <td><span class="badge text-bg-{{ ['high' => 'danger', 'normal' => 'primary', 'low' => 'secondary'][$r->priority] }}">{{ __(ucfirst($r->priority)) }}</span></td>
                    <td>@can('hostel.update')<form method="POST" action="{{ route('branch.hostel.maintenance.update', $r) }}" class="d-flex gap-1 flex-wrap">@csrf @method('PUT')
                        <select name="status" class="form-select form-select-sm" style="width:125px" aria-label="{{ __('Status') }}">@foreach(\App\Models\Hostel\MaintenanceRequest::STATUSES as $s)<option value="{{ $s }}" @selected($r->status === $s)>{{ __(ucfirst(str_replace('_', ' ', $s))) }}</option>@endforeach</select>
                        <input type="number" step="0.01" min="0" name="cost" value="{{ $r->cost }}" class="form-control form-control-sm" style="width:90px" placeholder="{{ __('Cost') }}" aria-label="{{ __('Cost') }}">
                        <input name="resolution" value="{{ $r->resolution }}" class="form-control form-control-sm" style="width:150px" maxlength="255" placeholder="{{ __('Resolution') }}" aria-label="{{ __('Resolution') }}"><button class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Save') }}"><i class="bi bi-check-lg"></i></button></form>
                        @else{{ __(ucfirst(str_replace('_', ' ', $r->status))) }}@endcan</td></tr>
            @endforeach</tbody></table></div><div class="card-footer bg-transparent">{{ $requests->links() }}</div>@endif
    </div></div>
    @can('hostel.create')
    <div class="col-lg-4"><form method="POST" action="{{ route('branch.hostel.maintenance.store') }}" class="card" x-data="{ h: '' }">@csrf
        <div class="card-header bg-transparent fw-semibold">{{ __('Log a request') }}</div>
        <div class="card-body row g-3">
            <x-form.select name="hostel_id" :label="__('Hostel')" :options="$hostels" required col="col-12" x-model="h" />
            <div class="col-12"><label class="form-label" for="hostel_room_id">{{ __('Room (optional)') }}</label><select id="hostel_room_id" name="hostel_room_id" class="form-select"><option value="">—</option>@foreach($rooms as $r)<option value="{{ $r->id }}" x-show="!h || h == '{{ $r->hostel_id }}'">{{ $r->room_no }}</option>@endforeach</select></div>
            <x-form.input name="title" :label="__('What needs fixing')" required col="col-12" maxlength="150" />
            <x-form.select name="priority" :label="__('Priority')" :options="['low' => __('Low'), 'normal' => __('Normal'), 'high' => __('High')]" value="normal" required col="col-12" />
            <div class="col-12"><label class="form-label" for="description">{{ __('Details') }}</label><textarea id="description" name="description" rows="2" maxlength="2000" class="form-control"></textarea></div>
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Log request') }}</button></div>
    </form></div>
    @endcan
</div>
@endsection
