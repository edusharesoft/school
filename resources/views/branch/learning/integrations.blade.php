@extends('layouts.branch')
@section('title', __('Zoom & Google Meet'))

@section('content')
<x-page-header :title="__('Zoom & Google Meet')" :breadcrumbs="[__('Learning') => null, __('Zoom & Google Meet') => null]" />

<p class="text-body-secondary small mb-3">{{ __('Connect this branch’s own Zoom or Google account. Secrets are stored encrypted and are never shown again after saving. Classes can always be scheduled with a pasted meeting link without any connection.') }}</p>

<div class="row g-3">
@foreach($rows as $key => $row)
    @php
        $def = $row['def'];
        $i = $row['integration'];
    @endphp
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><i class="bi {{ $key === 'zoom' ? 'bi-camera-video' : 'bi-google' }} me-1"></i>{{ $def['label'] }}</strong>
                @if($i?->is_enabled && $row['connected'])<span class="badge text-bg-success">{{ __('Ready') }}</span>
                @elseif($row['connected'])<span class="badge text-bg-secondary">{{ __('Disabled') }}</span>
                @else<span class="badge text-bg-warning">{{ __('Not connected') }}</span>@endif
            </div>
            <form method="POST" action="{{ route('branch.learning.integrations.update', $key) }}">
                @csrf @method('PUT')
                <div class="card-body row g-3">
                    <div class="col-12 small text-body-secondary">{{ __($def['help']) }}</div>
                    @foreach($def['fields'] as $field => $f)
                        @php($isSecret = ! empty($f['secret']))
                        <div class="col-12">
                            <label class="form-label" for="{{ $key }}_{{ $field }}">{{ __($f['label']) }}@if($f['required'] && ! $isSecret) <span class="text-danger">*</span>@endif</label>
                            <input id="{{ $key }}_{{ $field }}" name="{{ $field }}" type="{{ $isSecret ? 'password' : ($f['type'] === 'url' ? 'url' : 'text') }}" autocomplete="off" maxlength="500"
                                   value="{{ $isSecret ? '' : old($field, $i?->setting($field, $f['default'] ?? '')) }}"
                                   placeholder="{{ $isSecret && in_array($field, $row['secretsSet'], true) ? '••••••••  '.__('saved — leave blank to keep') : ($field === 'redirect_uri' ? $defaultRedirect : '') }}"
                                   class="form-control @error($field) is-invalid @enderror">
                            @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="{{ $key }}_enabled" name="is_enabled" value="1" @checked(old('is_enabled', $i?->is_enabled))><label class="form-check-label" for="{{ $key }}_enabled">{{ __('Allow teachers to schedule classes with :provider', ['provider' => $def['label']]) }}</label></div></div>
                    @if($i?->account_label)<div class="col-12 small">{{ __('Connected account') }}: <strong>{{ $i->account_label }}</strong></div>@endif
                    @if($i?->last_error)<div class="col-12"><div class="alert alert-danger small mb-0">{{ $i->last_error }}</div></div>@endif
                    @if($i?->last_checked_at && ! $i->last_error)<div class="col-12 small text-body-secondary">{{ __('Last checked') }}: {{ $i->last_checked_at->diffForHumans() }}</div>@endif
                </div>
                @can('integrations.configure')
                    <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
                        <button class="btn btn-brand btn-sm" type="submit">{{ __('Save') }}</button>
                        @if($i)
                            @if($key === 'google_meet')<a href="{{ route('branch.learning.integrations.google.connect') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>{{ $row['connected'] ? __('Reconnect Google account') : __('Connect Google account') }}</a>@endif
                            @if($row['connected'])<button class="btn btn-outline-secondary btn-sm" type="submit" form="test-{{ $key }}">{{ __('Test connection') }}</button>
                                <button class="btn btn-outline-danger btn-sm ms-auto" type="submit" form="disconnect-{{ $key }}" onclick="return confirm(@js(__('Disconnect :provider?', ['provider' => $def['label']])))">{{ __('Disconnect') }}</button>@endif
                        @endif
                    </div>
                @endcan
            </form>
            @if($i)
                <form id="test-{{ $key }}" method="POST" action="{{ route('branch.learning.integrations.test', $key) }}">@csrf</form>
                <form id="disconnect-{{ $key }}" method="POST" action="{{ route('branch.learning.integrations.disconnect', $key) }}">@csrf @method('DELETE')</form>
            @endif
        </div>
    </div>
@endforeach
</div>
@endsection
