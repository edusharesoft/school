@extends($layout)
@section('title', $notice->title)

@section('content')
<x-page-header :title="$notice->title" :breadcrumbs="[__('Notice board') => route('board.index'), __('Notice') => null]" />

<div class="card {{ $notice->is_emergency ? 'border-danger border-2' : '' }}" style="max-width: 860px">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 small mb-3">
            @if($notice->is_emergency)<span class="badge text-bg-danger">{{ __('Emergency') }}</span>@endif
            <span class="badge text-bg-light border">{{ __(ucfirst($notice->category)) }}</span>
            <span class="text-body-secondary">{{ ($notice->published_at ?? $notice->created_at)->format('d M Y, h:i A') }}</span>
            @if($notice->expires_at)<span class="text-body-secondary">· {{ __('Valid until :date', ['date' => $notice->expires_at->format('d M Y')]) }}</span>@endif
        </div>
        <div style="white-space: pre-line; line-height: 1.7">{{ $notice->body }}</div>
        @if($notice->attachment_path)
            <a href="{{ route('board.attachment', $notice) }}" class="btn btn-outline-secondary btn-sm mt-3"><i class="bi bi-paperclip me-1"></i>{{ $notice->attachment_name }}</a>
        @endif
    </div>
    <div class="card-footer bg-transparent small text-body-secondary">{{ tenant()->branch()?->displayName() }}</div>
</div>
@endsection
