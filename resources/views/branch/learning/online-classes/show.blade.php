@extends($layout)
@section('title', $class->title)

@section('content')
<x-page-header :title="$class->title" :breadcrumbs="[__('Online & offline classes') => route($routePrefix.'learning.online-classes.index'), $class->title => null]">
    @if($canManage && $class->status === 'scheduled')
        <x-slot:actions><a href="{{ route($routePrefix.'learning.online-classes.edit', $class) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a></x-slot:actions>
    @endif
</x-page-header>

@if($class->sync_status === 'failed')
    <div class="alert alert-danger d-flex flex-wrap gap-2 align-items-center" role="alert">
        <div class="me-auto"><strong>{{ __('The :provider meeting could not be created or updated.', ['provider' => $class->providerLabel()]) }}</strong><div class="small">{{ $class->sync_error }}</div></div>
        @if($canManage && $class->status === 'scheduled')
            <form method="POST" action="{{ route($routePrefix.'learning.online-classes.sync', $class) }}">@csrf<button class="btn btn-sm btn-danger"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Try again') }}</button></form>
        @endif
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card"><div class="card-body small">
            <div class="d-flex justify-content-between align-items-start mb-2"><span class="badge text-bg-{{ $class->tone() }} fs-6">{{ __(Illuminate\Support\Str::headline($class->phase())) }}</span>
                <span class="text-body-secondary">{{ $class->isOnline() ? $class->providerLabel() : __('Offline') }}</span></div>
            <dl class="row mb-0">
                <dt class="col-4">{{ __('When') }}</dt><dd class="col-8">{{ $class->starts_at->format('D, d M Y') }}<br>{{ $class->starts_at->format('H:i') }} – {{ $class->ends_at->format('H:i') }}</dd>
                <dt class="col-4">{{ __('Class') }}</dt><dd class="col-8">{{ $class->schoolClass?->name }}@if($class->section) — {{ $class->section->name }}@endif</dd>
                <dt class="col-4">{{ __('Subject') }}</dt><dd class="col-8">{{ $class->subject?->displayName() ?? '—' }}</dd>
                <dt class="col-4">{{ __('Teacher') }}</dt><dd class="col-8">{{ $class->teacher?->name ?? '—' }}</dd>
                @if($class->isOnline())
                    @if($class->join_url)<dt class="col-4">{{ __('Join link') }}</dt><dd class="col-8 text-break"><a href="{{ $class->join_url }}" target="_blank" rel="noopener noreferrer">{{ $class->join_url }}</a></dd>@endif
                    @if($class->host_url)<dt class="col-4">{{ __('Host link') }}</dt><dd class="col-8"><a href="{{ $class->host_url }}" target="_blank" rel="noopener noreferrer">{{ __('Start / manage the meeting') }}</a></dd>@endif
                    @if($class->meeting_id)<dt class="col-4">{{ __('Meeting ID') }}</dt><dd class="col-8">{{ $class->meeting_id }}</dd>@endif
                    @if($class->passcode)<dt class="col-4">{{ __('Passcode') }}</dt><dd class="col-8">{{ $class->passcode }}</dd>@endif
                @else
                    <dt class="col-4">{{ __('Room') }}</dt><dd class="col-8">{{ $class->room ?: __('In school') }}</dd>
                @endif
                @if($class->description)<dt class="col-4">{{ __('Description') }}</dt><dd class="col-8">{{ $class->description }}</dd>@endif
                @if($class->status === 'cancelled' && $class->cancel_reason)<dt class="col-4">{{ __('Reason') }}</dt><dd class="col-8">{{ $class->cancel_reason }}</dd>@endif
            </dl>
        </div></div>

        @if($canManage && $class->status !== 'cancelled')
            <div class="card mt-3"><div class="card-body small">
                <h2 class="h6">{{ __('Recording') }}</h2>
                @if($class->recording_url)<p class="mb-2"><a href="{{ $class->recording_url }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-play-circle me-1"></i>{{ __('Open recording') }}</a></p>@endif
                <form method="POST" action="{{ route($routePrefix.'learning.online-classes.recording', $class) }}" class="d-flex gap-2">
                    @csrf
                    <input type="url" name="recording_url" value="{{ old('recording_url', $class->recording_url) }}" placeholder="https://" class="form-control form-control-sm" aria-label="{{ __('Recording link') }}" maxlength="500">
                    <button class="btn btn-sm btn-outline-secondary">{{ __('Save') }}</button>
                </form>
                @if($class->provider === 'zoom' && $class->external_id)
                    <form method="POST" action="{{ route($routePrefix.'learning.online-classes.recording', $class) }}" class="mt-2">@csrf<input type="hidden" name="fetch" value="1"><button class="btn btn-sm btn-link p-0">{{ __('Fetch the Zoom cloud recording') }}</button></form>
                @endif
            </div></div>
        @endif

        @if($canManage && $class->status === 'scheduled')
            <div class="card mt-3"><div class="card-body small">
                <form method="POST" action="{{ route($routePrefix.'learning.online-classes.complete', $class) }}" class="mb-3">@csrf
                    <button class="btn btn-sm btn-success"><i class="bi bi-check2-circle me-1"></i>{{ __('Mark as completed') }}</button></form>
                <form method="POST" action="{{ route($routePrefix.'learning.online-classes.cancel', $class) }}" onsubmit="return confirm(@js(__('Cancel this class? Students and guardians will be notified.')))">@csrf
                    <label class="form-label" for="reason">{{ __('Cancel this class') }}</label>
                    <div class="input-group input-group-sm"><input id="reason" name="reason" class="form-control" maxlength="255" placeholder="{{ __('Reason (shown to students)') }}"><button class="btn btn-outline-danger">{{ __('Cancel class') }}</button></div>
                </form>
            </div></div>
        @endif
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between"><strong>{{ __('Attendance') }}</strong>
                <span class="small text-body-secondary">{{ __('Present') }} {{ $counts->get('present', 0) + $counts->get('late', 0) }} · {{ __('Absent') }} {{ $counts->get('absent', 0) }} · {{ __('Not marked') }} {{ $counts->get('unmarked', 0) }}</span></div>
            @if($roster->isEmpty())
                <div class="card-body text-body-secondary">{{ __('No students are enrolled in this class yet.') }}</div>
            @else
                <form method="POST" action="{{ route($routePrefix.'learning.online-classes.attendance', $class) }}">@csrf
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
                        <thead><tr><th>{{ __('Roll') }}</th><th>{{ __('Student') }}</th><th style="width:160px">{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @foreach($roster as $r)
                            <tr><td>{{ $r['student']->currentEnrollment?->roll_no }}</td><td>{{ $r['student']->name }}</td>
                                <td>@if($canManage && $class->status !== 'cancelled')
                                    <select name="status[{{ $r['student']->id }}]" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                                        <option value="">—</option>
                                        @foreach(App\Models\Learning\OnlineClassAttendance::STATUSES as $st)<option value="{{ $st }}" @selected($r['status'] === $st)>{{ __(ucfirst($st)) }}</option>@endforeach
                                    </select>
                                    @else{{ $r['status'] ? __(ucfirst($r['status'])) : '—' }}@endif</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    @if($canManage && $class->status !== 'cancelled')<div class="card-footer bg-transparent"><button class="btn btn-brand btn-sm">{{ __('Save attendance') }}</button></div>@endif
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
