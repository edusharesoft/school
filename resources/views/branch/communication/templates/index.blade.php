@extends('layouts.branch')
@section('title', __('Message templates'))

@section('content')
<x-page-header :title="__('Message templates')" :breadcrumbs="[__('Communication') => null, __('Message templates') => null]" />

<div class="alert alert-light border small"><i class="fa-solid fa-circle-info text-primary me-1"></i>
    {{ __('Every notification has a Bangla and an English text. Each recipient gets the language of their portal account, otherwise the branch default. Change the wording here; blank fields use the default.') }}</div>

<div class="card">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0 small">
        <thead><tr><th>{{ __('Notification') }}</th><th>{{ __('Sent to') }}</th><th>{{ __('Variables') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($events as $key => $e)
            <tr>
                <td><a href="{{ route('branch.communication.templates.edit', $key) }}" class="fw-semibold text-decoration-none">{{ __($e['label']) }}</a>
                    @if($customised->has($key))<span class="badge text-bg-info ms-1">{{ __('Customised') }}</span>@endif
                    <div class="text-body-secondary"><code>{{ $key }}</code></div></td>
                <td>{{ __(Illuminate\Support\Str::headline($e['recipients'])) }}</td>
                <td>@foreach($e['vars'] as $v)<code class="me-1">{{ '{'.$v.'}' }}</code>@endforeach</td>
                <td class="text-end"><a href="{{ route('branch.communication.templates.edit', $key) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
