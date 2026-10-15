@extends('layouts.portal')
@section('title', __('Online classes'))

@section('content')
<x-page-header :title="__('Online classes')" :breadcrumbs="$mode === 'parent' ? [__('My children') => route('portal.parent'), $student->name => null] : [__('Learning') => null]" />

<div class="dp blue mb-3">
    <div class="dp-head"><h2><i class="fa-solid fa-video me-1"></i> {{ __('Upcoming classes') }}</h2><span class="sub">{{ bn_digits($upcoming->count()) }}</span></div>
    <div class="dp-body">
        @forelse($upcoming as $c)
            <div class="d-flex flex-wrap align-items-start gap-3 border-bottom py-2">
                <div class="text-center" style="min-width:64px"><div class="fw-bold fs-4 lh-1">{{ bn_digits($c->starts_at->format('d')) }}</div><div class="small text-body-secondary">{{ $c->starts_at->format('M') }}</div></div>
                <div class="me-auto">
                    <div class="fw-semibold">{{ $c->title }}</div>
                    <div class="small text-body-secondary">{{ $c->subject?->displayName() ?? '—' }} · {{ $c->teacher?->name }}</div>
                    <div class="small">{{ $c->starts_at->format('D H:i') }} – {{ $c->ends_at->format('H:i') }} ·
                        @if($c->isOnline())<i class="bi bi-camera-video"></i> {{ $c->providerLabel() }}@else<i class="bi bi-building"></i> {{ $c->room ?: __('In school') }}@endif</div>
                    @if($c->description)<div class="small text-body-secondary">{{ $c->description }}</div>@endif
                </div>
                @if($c->isOnline())
                    @if($c->joinable())
                        <div class="text-end"><a href="{{ $c->join_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-success"><i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Join class') }}</a>
                            @if($c->meeting_id || $c->passcode)<div class="small text-body-secondary mt-1">@if($c->meeting_id){{ __('ID') }} {{ $c->meeting_id }}@endif @if($c->passcode) · {{ __('Passcode') }} {{ $c->passcode }}@endif</div>@endif</div>
                    @else
                        <span class="badge text-bg-light border">{{ __('Link opens 15 minutes before the class') }}</span>
                    @endif
                @endif
            </div>
        @empty
            <div class="text-center text-body-secondary py-3">{{ __('No classes are scheduled right now.') }}</div>
        @endforelse
    </div>
</div>

@if($past->isNotEmpty())
<div class="dp blue">
    <div class="dp-head"><h2><i class="fa-solid fa-clock-rotate-left me-1"></i> {{ __('Recent classes') }}</h2><span class="sub">{{ __('Last 30 days') }}</span></div>
    <div class="dp-body">
        @foreach($past as $c)
            <div class="d-flex flex-wrap align-items-start gap-3 border-bottom py-2">
                <div class="me-auto">
                    <div class="fw-semibold">{{ $c->title }} <span class="badge text-bg-{{ $c->tone() }}">{{ __(Illuminate\Support\Str::headline($c->phase())) }}</span></div>
                    <div class="small text-body-secondary">{{ $c->starts_at->format('d M Y H:i') }} · {{ $c->subject?->displayName() ?? '—' }}@if($c->status === 'cancelled' && $c->cancel_reason) · {{ $c->cancel_reason }}@endif</div>
                </div>
                @if($attendance->has($c->id))<span class="badge text-bg-{{ in_array($attendance[$c->id], ['present', 'late'], true) ? 'success' : ($attendance[$c->id] === 'excused' ? 'info' : 'danger') }}">{{ __(ucfirst($attendance[$c->id])) }}</span>@endif
                @if($c->recording_url && $c->status !== 'cancelled')<a href="{{ $c->recording_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="bi bi-play-circle me-1"></i>{{ __('Recording') }}</a>@endif
            </div>
        @endforeach
    </div>
</div>
@endif
@endsection
