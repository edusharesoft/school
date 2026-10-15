@extends('layouts.branch')
@section('title', __('Hostel roll call'))

@section('content')
<x-page-header :title="__('Hostel roll call')" :breadcrumbs="[__('Hostel') => route('branch.hostel.index'), __('Roll call') => null]" />
<form method="GET" class="mb-3 d-flex gap-2 align-items-end">
    <div><label class="form-label small mb-0" for="hostel_id">{{ __('Hostel') }}</label><select id="hostel_id" name="hostel_id" class="form-select form-select-sm">@foreach($hostels as $h)<option value="{{ $h->id }}" @selected($hostel?->id === $h->id)>{{ $h->name }}</option>@endforeach</select></div>
    <div><label class="form-label small mb-0" for="date">{{ __('Date') }}</label><input id="date" type="date" name="date" value="{{ $date }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm"></div>
    <button class="btn btn-sm btn-outline-secondary">{{ __('Show') }}</button>
</form>
<form method="POST" action="{{ route('branch.hostel.attendance.save') }}" class="card">@csrf
    @if(! $hostel || $residents->isEmpty())<x-empty-state icon="people" :title="__('No residents to call')" />
    @else<input type="hidden" name="hostel_id" value="{{ $hostel->id }}"><input type="hidden" name="date" value="{{ $date }}">
    <div class="table-responsive"><table class="table align-middle mb-0 small"><thead><tr><th>{{ __('Room / bed') }}</th><th>{{ __('Student') }}</th><th>{{ __('Status') }}</th><th>{{ __('Note') }}</th></tr></thead><tbody>
        @foreach($residents as $a)
            @php($m = $marks->get($a->student_id))
            <tr><td>{{ $a->room?->room_no }} · {{ $a->bed?->label }}</td><td>{{ $a->student?->name }} <span class="text-body-secondary">{{ $a->student?->student_code }}</span></td>
                <td><select name="status[{{ $a->student_id }}]" class="form-select form-select-sm" aria-label="{{ __('Status') }}" @cannot('hostel.update') disabled @endcannot>@foreach(\App\Models\Hostel\Attendance::STATUSES as $s)<option value="{{ $s }}" @selected(($m?->status ?? 'present') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach</select></td>
                <td><input name="note[{{ $a->student_id }}]" value="{{ $m?->note }}" maxlength="255" class="form-control form-control-sm" aria-label="{{ __('Note') }}"></td></tr>
        @endforeach</tbody></table></div>
    @can('hostel.update')<div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Save roll call') }}</button></div>@endcan
    @endif
</form>
@endsection
