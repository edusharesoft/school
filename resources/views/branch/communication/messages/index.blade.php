@extends('layouts.branch')
@section('title', __('Message history'))

@php
    $channelLabel = fn ($c) => __($c === 'email' ? 'E-mail' : ($c === 'whatsapp' ? 'WhatsApp' : ($c === 'in_app' ? 'In-app' : 'SMS')));
@endphp

@section('content')
<x-page-header :title="__('Message history')" :breadcrumbs="[__('Communication') => null, __('Message history') => null]">
    <x-slot:actions>
        <a href="{{ route('branch.communication.messages.usage') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bar-chart me-1"></i>{{ __('Usage report') }}</a>
        @can('notifications.send')
            @if(($counts['failed'] ?? 0) > 0)
                <form method="POST" action="{{ route('branch.communication.messages.retry-failed', array_filter(['batch' => request('batch')])) }}" data-confirm="{{ __('Queue all failed messages of the last 7 days again?') }}">@csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Retry failed') }}</button></form>
            @endif
            <a href="{{ route('branch.communication.messages.create') }}" class="btn btn-brand btn-sm"><i class="bi bi-send me-1"></i>{{ __('Send message') }}</a>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3 mb-3">
    @foreach(['sent' => ['success', 'fa-solid fa-check'], 'delivered' => ['success', 'fa-solid fa-check-double'], 'queued' => ['warning', 'fa-solid fa-hourglass-half'], 'scheduled' => ['info', 'fa-solid fa-calendar'], 'failed' => ['danger', 'fa-solid fa-triangle-exclamation'], 'skipped' => ['secondary', 'fa-solid fa-ban']] as $st => [$tone, $icon])
        <div class="col-6 col-md-2">
            <a href="{{ request()->fullUrlWithQuery(['status' => $st]) }}" class="card h-100 text-decoration-none"><div class="card-body py-2">
                <div class="small text-body-secondary"><i class="{{ $icon }} text-{{ $tone }} me-1"></i>{{ __(ucfirst($st)) }}</div>
                <div class="fs-5 fw-semibold">{{ $counts[$st] ?? 0 }}</div>
            </div></a>
        </div>
    @endforeach
</div>

@if($batches->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header bg-transparent fw-semibold small">{{ __('Recent bulk messages') }}</div>
        <div class="table-responsive"><table class="table table-sm mb-0 small align-middle">
            <tbody>
            @foreach($batches as $b)
                <tr>
                    <td><a href="{{ route('branch.communication.messages.index', ['batch' => $b->id, 'from' => $b->created_at->toDateString()]) }}" class="fw-semibold text-decoration-none">{{ $b->title }}</a>
                        @if($b->is_emergency)<span class="badge text-bg-danger ms-1">{{ __('Emergency') }}</span>@endif
                        <div class="text-body-secondary">{{ $b->creator?->name }} · {{ $b->created_at->format('d M Y H:i') }}</div></td>
                    <td>{{ collect($b->channels)->map($channelLabel)->implode(', ') }}</td>
                    <td>{{ trans_choice(':count recipient|:count recipients', $b->recipients, ['count' => $b->recipients]) }}</td>
                    <td>@if($b->scheduled_at){{ __('Scheduled for :time', ['time' => $b->scheduled_at->format('d M Y H:i')]) }}@endif</td>
                    <td class="text-end">@if($b->scheduled_at?->isFuture())@can('notifications.send')
                        <form method="POST" action="{{ route('branch.communication.messages.batches.cancel', $b) }}" data-confirm="{{ __('Cancel the scheduled messages of this batch?') }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Cancel') }}</button></form>@endcan @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@endif

<div class="card">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            @if(request('batch'))<input type="hidden" name="batch" value="{{ request('batch') }}">@endif
            <div class="col-md-2"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><select name="channel" class="form-select form-select-sm" aria-label="{{ __('Channel') }}"><option value="">{{ __('All channels') }}</option>
                @foreach(['sms', 'email', 'whatsapp'] as $c)<option value="{{ $c }}" @selected(request('channel') === $c)>{{ $channelLabel($c) }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>
                @foreach(App\Models\Communication\NotificationMessage::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="event" class="form-select form-select-sm" aria-label="{{ __('Type') }}"><option value="">{{ __('All types') }}</option>
                @foreach(config('notifications.events') as $key => $e)<option value="{{ $key }}" @selected(request('event') === $key)>{{ __($e['label']) }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Name or number') }}" aria-label="{{ __('Name or number') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button></div>
        </form>
    </div>
    @if($messages->isEmpty())
        <x-empty-state icon="chat-left-text" :title="__('No messages in this period')" />
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead><tr><th>{{ __('Time') }}</th><th>{{ __('Recipient') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Message') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($messages as $m)
                    <tr>
                        <td class="text-nowrap">{{ ($m->scheduled_at && $m->status === 'scheduled' ? $m->scheduled_at : $m->created_at)->format('d M H:i') }}</td>
                        <td>{{ $m->recipient_name }}<div class="text-body-secondary">{{ $m->to }}</div></td>
                        <td>{{ $channelLabel($m->channel) }}@if($m->segments > 1) <span class="text-body-secondary">×{{ $m->segments }}</span>@endif</td>
                        <td><a href="{{ route('branch.communication.messages.show', $m) }}" class="text-decoration-none text-body">{{ Illuminate\Support\Str::limit($m->subject && $m->channel !== 'sms' ? $m->subject : $m->body, 70) }}</a>
                            <div class="text-body-secondary">{{ __(App\Services\Notifications\EventCatalog::label($m->event)) }}</div></td>
                        <td><span class="badge text-bg-{{ $m->tone() }}">{{ __(ucfirst($m->status)) }}</span>@if($m->error)<div class="text-danger text-truncate" style="max-width: 220px" title="{{ $m->error }}">{{ $m->error }}</div>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $messages->links() }}</div>
    @endif
</div>
@endsection
