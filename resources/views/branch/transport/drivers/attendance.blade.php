@extends('layouts.branch')
@section('title', __('Driver attendance'))

@section('content')
<x-page-header :title="__('Driver attendance')" :breadcrumbs="[__('Transport') => route('branch.transport.index'), __('Attendance') => null]" />
<form method="GET" class="mb-3 d-flex gap-2 align-items-end"><div><label class="form-label small mb-0" for="date">{{ __('Date') }}</label><input id="date" type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div><button class="btn btn-sm btn-outline-secondary">{{ __('Show') }}</button></form>
<form method="POST" action="{{ route('branch.transport.attendance.save') }}" class="card">@csrf<input type="hidden" name="date" value="{{ $date }}">
    @if($drivers->isEmpty())<x-empty-state icon="person-badge" :title="__('No active drivers')" />
    @else<div class="table-responsive"><table class="table align-middle mb-0 small"><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Role') }}</th><th>{{ __('Status') }}</th><th>{{ __('Note') }}</th></tr></thead><tbody>
        @foreach($drivers as $d)
            @php($m = $marks->get($d->id))
            <tr><td>{{ $d->name }}</td><td>{{ __(ucfirst($d->role)) }}</td>
                <td><select name="status[{{ $d->id }}]" class="form-select form-select-sm" aria-label="{{ __('Status') }}" @cannot('transport.update') disabled @endcannot>@foreach(['present', 'absent', 'leave'] as $s)<option value="{{ $s }}" @selected(($m?->status ?? 'present') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach</select></td>
                <td><input name="note[{{ $d->id }}]" value="{{ $m?->note }}" maxlength="255" class="form-control form-control-sm" aria-label="{{ __('Note') }}"></td></tr>
        @endforeach</tbody></table></div>
        @can('transport.update')<div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Save attendance') }}</button></div>@endcan
    @endif
</form>
@endsection
