@extends('layouts.public')
@section('title', __('Admission result').' — '.$campaign->title)

@section('content')
<h1 class="h4 mb-1">{{ __('Admission result') }}: {{ $campaign->title }}</h1>
<p class="text-body-secondary">{{ $campaign->schoolClass?->name }} · {{ __('Only application numbers are published.') }}</p>
<div class="row g-4">
    <div class="col-md-6">
        <div class="dp green">
            <div class="dp-head"><h2><i class="fa-solid fa-check me-1"></i> {{ __('Selected') }} ({{ bn_digits($selected->count()) }})</h2></div>
            <div class="dp-body p-0"><table class="table table-sm mb-0"><thead><tr><th>{{ __('Position') }}</th><th>{{ __('Application no.') }}</th></tr></thead>
                <tbody>@foreach($selected as $a)<tr><td>{{ bn_digits($a->merit_position) }}</td><td><code>{{ $a->application_no }}</code></td></tr>@endforeach</tbody></table></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="dp orange">
            <div class="dp-head"><h2><i class="fa-solid fa-hourglass-half me-1"></i> {{ __('Waiting list') }} ({{ bn_digits($waiting->count()) }})</h2></div>
            <div class="dp-body p-0"><table class="table table-sm mb-0"><thead><tr><th>{{ __('Position') }}</th><th>{{ __('Application no.') }}</th></tr></thead>
                <tbody>@foreach($waiting as $a)<tr><td>{{ bn_digits($a->waiting_position) }}</td><td><code>{{ $a->application_no }}</code></td></tr>@endforeach</tbody></table></div>
        </div>
    </div>
</div>
@endsection
