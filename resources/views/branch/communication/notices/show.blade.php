@extends('layouts.branch')
@section('title', $notice->title)

@section('content')
<x-page-header :title="$notice->title" :breadcrumbs="[__('Notices') => route('branch.communication.notices.index'), __('Notice') => null]">
    <x-slot:actions>
        @if($notice->status !== 'archived')
            @can('notices.update')<a href="{{ route('branch.communication.notices.edit', $notice) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('Edit') }}</a>@endcan
        @endif
        @if(in_array($notice->status, ['draft', 'scheduled'], true))
            @can('notices.publish')
                <form method="POST" action="{{ route('branch.communication.notices.publish', $notice) }}" data-confirm="{{ __('Publish this notice? The audience will be notified.') }}">@csrf
                    <button class="btn btn-brand btn-sm"><i class="bi bi-send me-1"></i>{{ $notice->publish_at?->isFuture() ? __('Schedule') : __('Publish now') }}</button></form>
            @endcan
        @endif
        @if($notice->status === 'scheduled')
            @can('notices.publish')
                <form method="POST" action="{{ route('branch.communication.notices.unschedule', $notice) }}">@csrf<button class="btn btn-outline-warning btn-sm">{{ __('Back to draft') }}</button></form>
            @endcan
        @endif
        @if($notice->status === 'published')
            @can('notices.update')
                <form method="POST" action="{{ route('branch.communication.notices.archive', $notice) }}" data-confirm="{{ __('Archive this notice? It disappears from the board.') }}">@csrf<button class="btn btn-outline-danger btn-sm">{{ __('Archive') }}</button></form>
            @endcan
        @endif
        @if($notice->status === 'draft')
            @can('notices.delete')
                <form method="POST" action="{{ route('branch.communication.notices.destroy', $notice) }}" data-confirm="{{ __('Delete this draft?') }}">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form>
            @endcan
        @endif
    </x-slot:actions>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card @if($notice->is_emergency) border-danger @endif">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-2 small">
                    <span class="badge text-bg-{{ $notice->tone() }}">{{ __(ucfirst($notice->displayStatus())) }}</span>
                    <span class="badge text-bg-light border">{{ __(ucfirst($notice->category)) }}</span>
                    @if($notice->is_emergency)<span class="badge text-bg-danger">{{ __('Emergency') }}</span>@endif
                    @if($notice->is_pinned)<span class="badge text-bg-warning">{{ __('Pinned') }}</span>@endif
                    @if($notice->is_public)<span class="badge text-bg-info">{{ __('Public') }}</span>@endif
                </div>
                <div style="white-space: pre-line">{{ $notice->body }}</div>
                @if($notice->attachment_path)
                    <a href="{{ route('branch.communication.notices.attachment', $notice) }}" class="btn btn-sm btn-outline-secondary mt-3"><i class="bi bi-paperclip me-1"></i>{{ $notice->attachment_name }}</a>
                @endif
            </div>
            <div class="card-footer bg-transparent small text-body-secondary">
                {{ __('Written by :name', ['name' => $notice->creator?->name ?? '—']) }} · {{ $notice->created_at->format('d M Y H:i') }}
                @if($notice->publisher) · {{ __('Published by :name', ['name' => $notice->publisher->name]) }}@endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body small">
            <dl class="row mb-0">
                <dt class="col-5">{{ __('Audience') }}</dt><dd class="col-7">{{ $audienceLabel }}</dd>
                <dt class="col-5">{{ __('Publish') }}</dt><dd class="col-7">{{ ($notice->published_at ?? $notice->publish_at)?->format('d M Y H:i') ?? __('When published') }}</dd>
                <dt class="col-5">{{ __('Expires') }}</dt><dd class="col-7">{{ $notice->expires_at?->format('d M Y H:i') ?? __('Never') }}</dd>
                <dt class="col-5">{{ __('Also sent by') }}</dt><dd class="col-7">{{ collect($notice->channels ?? [])->map(fn ($c) => __(strtoupper($c) === 'EMAIL' ? 'E-mail' : ($c === 'whatsapp' ? 'WhatsApp' : 'SMS')))->implode(', ') ?: '—' }}</dd>
            </dl>
        </div></div>
        @if($stats)
            <div class="card mb-3"><div class="card-body">
                <div class="small text-body-secondary">{{ __('Read receipts') }}</div>
                <div class="fs-4 fw-semibold">{{ $stats['read'] }} <span class="fs-6 text-body-secondary">/ {{ $stats['audience'] }}</span></div>
                <div class="progress" style="height:6px" role="progressbar" aria-label="{{ __('Read receipts') }}" aria-valuenow="{{ $stats['audience'] ? round($stats['read'] / $stats['audience'] * 100) : 0 }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: {{ $stats['audience'] ? round($stats['read'] / $stats['audience'] * 100) : 0 }}%"></div></div>
                <div class="small text-body-secondary mt-1">{{ __('People in the audience with a portal login who opened it.') }}</div>
            </div></div>
        @endif
        @if($delivery->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header bg-transparent fw-semibold small">{{ __('Delivery') }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach($delivery->groupBy('channel') as $channel => $rows)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ __($channel === 'in_app' ? 'In-app' : ($channel === 'email' ? 'E-mail' : ($channel === 'whatsapp' ? 'WhatsApp' : 'SMS'))) }}</span>
                            <span>@foreach($rows as $r)<span class="badge text-bg-light border ms-1">{{ __($r->status) }}: {{ $r->n }}</span>@endforeach</span></li>
                    @endforeach
                </ul>
                @can('notifications.view')<div class="card-footer bg-transparent small"><a href="{{ route('branch.communication.messages.index', ['event' => 'notice.published']) }}">{{ __('Message history') }}</a></div>@endcan
            </div>
        @endif
        @if($readers->isNotEmpty())
            <div class="card">
                <div class="card-header bg-transparent fw-semibold small">{{ __('Recently read by') }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach($readers as $r)<li class="list-group-item d-flex justify-content-between"><span>{{ $r->user?->name }}</span><span class="text-body-secondary">{{ $r->read_at->format('d M H:i') }}</span></li>@endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
