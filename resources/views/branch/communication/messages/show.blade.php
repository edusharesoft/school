@extends('layouts.branch')
@section('title', __('Message'))

@section('content')
<x-page-header :title="__('Message').' #'.$message->id" :breadcrumbs="[__('Message history') => route('branch.communication.messages.index'), '#'.$message->id => null]">
    <x-slot:actions>
        @can('notifications.send')
            @if(in_array($message->status, ['failed', 'cancelled'], true) && $message->channel !== 'in_app')
                <form method="POST" action="{{ route('branch.communication.messages.retry', $message) }}">@csrf<button class="btn btn-brand btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Retry') }}</button></form>
            @endif
            @if(in_array($message->status, ['scheduled', 'queued'], true))
                <form method="POST" action="{{ route('branch.communication.messages.cancel', $message) }}" data-confirm="{{ __('Cancel this message?') }}">@csrf<button class="btn btn-outline-danger btn-sm">{{ __('Cancel') }}</button></form>
            @endif
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            @if($message->subject && $message->channel !== 'sms')<div class="fw-semibold mb-2">{{ $message->subject }}</div>@endif
            <div style="white-space: pre-line">{{ $message->body }}</div>
            @if($message->channel === 'whatsapp' && $message->payload)
                <hr><div class="small text-body-secondary">{{ __('WhatsApp template') }}: <code>{{ $message->payload['template'] ?? '—' }}</code> ({{ $message->payload['language'] ?? '' }})
                    — {{ __('parameters') }}: {{ implode(' | ', $message->payload['parameters'] ?? []) }}</div>
            @endif
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><span class="badge text-bg-{{ $message->tone() }}">{{ __(ucfirst($message->status)) }}</span></dd>
                @if($message->error)<dt class="col-5">{{ __('Error') }}</dt><dd class="col-7 text-danger">{{ $message->error }}</dd>@endif
                <dt class="col-5">{{ __('Recipient') }}</dt><dd class="col-7">{{ $message->recipient_name }} <span class="text-body-secondary">({{ __(ucfirst((string) $message->recipient_type)) }})</span></dd>
                <dt class="col-5">{{ __('To') }}</dt><dd class="col-7">{{ $message->to }}</dd>
                <dt class="col-5">{{ __('Channel') }}</dt><dd class="col-7">{{ strtoupper($message->channel) }}@if($message->segments) · {{ trans_choice(':count part|:count parts', $message->segments, ['count' => $message->segments]) }}@endif</dd>
                <dt class="col-5">{{ __('Type') }}</dt><dd class="col-7">{{ __(App\Services\Notifications\EventCatalog::label($message->event)) }}</dd>
                <dt class="col-5">{{ __('Language') }}</dt><dd class="col-7">{{ $message->locale === 'bn' ? 'বাংলা' : 'English' }}</dd>
                <dt class="col-5">{{ __('Provider') }}</dt><dd class="col-7">{{ $message->provider ?? '—' }}@if($message->provider_message_id)<div class="text-body-secondary text-break">{{ $message->provider_message_id }}</div>@endif</dd>
                <dt class="col-5">{{ __('Attempts') }}</dt><dd class="col-7">{{ $message->attempts }}</dd>
                <dt class="col-5">{{ __('Created') }}</dt><dd class="col-7">{{ $message->created_at->format('d M Y H:i:s') }}</dd>
                @if($message->scheduled_at)<dt class="col-5">{{ __('Scheduled') }}</dt><dd class="col-7">{{ $message->scheduled_at->format('d M Y H:i') }}</dd>@endif
                @if($message->sent_at)<dt class="col-5">{{ __('Sent') }}</dt><dd class="col-7">{{ $message->sent_at->format('d M Y H:i:s') }}</dd>@endif
                @if($message->delivered_at)<dt class="col-5">{{ __('Delivered') }}</dt><dd class="col-7">{{ $message->delivered_at->format('d M Y H:i:s') }}</dd>@endif
                @if($message->read_at)<dt class="col-5">{{ __('Read') }}</dt><dd class="col-7">{{ $message->read_at->format('d M Y H:i:s') }}</dd>@endif
                @if($message->batch)<dt class="col-5">{{ __('Bulk message') }}</dt><dd class="col-7"><a href="{{ route('branch.communication.messages.index', ['batch' => $message->batch->id, 'from' => $message->batch->created_at->toDateString()]) }}">{{ $message->batch->title }}</a></dd>@endif
            </dl>
        </div></div>
    </div>
</div>
@endsection
