@extends('layouts.central')
@section('title', __('System health'))

@section('content')
<x-page-header :title="__('System health')" :breadcrumbs="[__('System') => null, __('System health') => null]">
    <x-slot:actions><a href="{{ route('central.system.health') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Check again') }}</a></x-slot:actions>
</x-page-header>
@php($tone = ['ok' => 'success', 'warn' => 'warning', 'fail' => 'danger'])
<div class="alert alert-{{ $tone[$result['status']] }}">
    <strong>{{ ['ok' => __('Everything is working.'), 'warn' => __('Working, but something needs attention.'), 'fail' => __('Something is broken.')][$result['status']] }}</strong>
    <span class="ms-2 small">{{ \Illuminate\Support\Carbon::parse($result['at'])->format('d M Y, h:i A') }}</span>
</div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <tbody>
    @foreach($result['checks'] as $c)
        <tr><td style="width:12rem" class="fw-semibold">{{ $c['label'] }}</td><td style="width:6rem"><span class="badge text-bg-{{ $tone[$c['status']] }}">{{ strtoupper($c['status']) }}</span></td><td>{{ $c['detail'] }}</td></tr>
    @endforeach
    </tbody></table></div></div>
<p class="small text-body-secondary mt-3">{{ __('For an outside uptime monitor set HEALTH_TOKEN and watch /health/status?token=… — it answers 503 when a check fails.') }}</p>
@endsection
