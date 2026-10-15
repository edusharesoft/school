@extends('layouts.branch')
@section('title', __('Message usage'))

@section('content')
<x-page-header :title="__('Message usage')" :breadcrumbs="[__('Message history') => route('branch.communication.messages.index'), __('Usage report') => null]" />

<div class="card mb-3 no-print"><div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small" for="f_from">{{ __('From') }}</label><input id="f_from" type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-md-3"><label class="form-label small" for="f_to">{{ __('To') }}</label><input id="f_to" type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm"></div>
        <div class="col-auto"><button class="btn btn-sm btn-brand">{{ __('Show') }}</button></div>
        <div class="col-auto ms-auto"><button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>{{ __('Print') }}</button></div>
    </form>
</div></div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg"><x-stat-card :label="__('SMS sent')" :value="$totals['sms']" icon="fa-solid fa-comment-sms" bg="#dbeafe" color="#1d4ed8" :sub="trans_choice(':count billable part|:count billable parts', $totals['sms_parts'], ['count' => $totals['sms_parts']])" /></div>
    <div class="col-6 col-lg"><x-stat-card :label="__('E-mails sent')" :value="$totals['email']" icon="fa-solid fa-envelope" bg="#dcfce7" color="#15803d" /></div>
    <div class="col-6 col-lg"><x-stat-card :label="__('WhatsApp sent')" :value="$totals['whatsapp']" icon="fa-brands fa-whatsapp" bg="#d1fae5" color="#047857" /></div>
    <div class="col-6 col-lg"><x-stat-card :label="__('Failed')" :value="$totals['failed']" icon="fa-solid fa-triangle-exclamation" bg="#fee2e2" color="#b91c1c" /></div>
</div>

<div class="card mb-3">
    <div class="card-header bg-transparent fw-semibold">{{ __('By month') }}</div>
    <div class="table-responsive"><table class="table table-sm mb-0 small align-middle">
        <thead class="table-light"><tr><th>{{ __('Month') }}</th><th>{{ __('Channel') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Sent') }}</th><th class="text-end">{{ __('Failed') }}</th><th class="text-end">{{ __('Skipped') }}</th><th class="text-end">{{ __('SMS parts') }}</th></tr></thead>
        <tbody>
        @forelse($months as $month => $m)
            @foreach($m['by_channel'] as $channel => $c)
                <tr>
                    <td>@if($loop->first){{ Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->translatedFormat('F Y') }}@endif</td>
                    <td>{{ __($channel === 'in_app' ? 'In-app' : ($channel === 'email' ? 'E-mail' : ($channel === 'whatsapp' ? 'WhatsApp' : 'SMS'))) }}</td>
                    <td class="text-end">{{ $c['total'] }}</td><td class="text-end">{{ $c['sent'] }}</td><td class="text-end">{{ $c['failed'] }}</td><td class="text-end">{{ $c['skipped'] }}</td>
                    <td class="text-end">@if($channel === 'sms'){{ $m['sms_parts'] }}@endif</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="7" class="text-center text-body-secondary py-3">{{ __('No messages in this period') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header bg-transparent fw-semibold">{{ __('By type') }}</div>
    <div class="table-responsive"><table class="table table-sm mb-0 small">
        <thead class="table-light"><tr><th>{{ __('Type') }}</th>@foreach(['in_app', 'sms', 'email', 'whatsapp'] as $c)<th class="text-end">{{ __($c === 'in_app' ? 'In-app' : ($c === 'email' ? 'E-mail' : ($c === 'whatsapp' ? 'WhatsApp' : 'SMS'))) }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach($byEvent as $event => $counts)
            <tr><td>{{ __(App\Services\Notifications\EventCatalog::label($event)) }}</td>@foreach(['in_app', 'sms', 'email', 'whatsapp'] as $c)<td class="text-end">{{ $counts[$c] ?? 0 }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
