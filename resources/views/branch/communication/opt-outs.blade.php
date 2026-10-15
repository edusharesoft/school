@extends('layouts.branch')
@section('title', __('Opt-outs'))

@section('content')
<x-page-header :title="__('Opt-outs')" :breadcrumbs="[__('Communication') => null, __('Opt-outs') => null]" />

<div class="alert alert-light border small"><i class="fa-solid fa-circle-info text-primary me-1"></i>
    {{ __('These numbers and addresses do not receive routine messages on the channel. Emergency notices and messages are still delivered. Guardians and students can also change this themselves in their portal under Message preferences.') }}</div>

<div class="row g-3">
    @can('notifications.configure')
    <div class="col-lg-4">
        <form method="POST" action="{{ route('branch.communication.opt-outs.store') }}" class="card">
            @csrf
            <div class="card-header bg-transparent fw-semibold">{{ __('Add opt-out') }}</div>
            <div class="card-body row g-2">
                <x-form.select name="channel" :label="__('Channel')" :options="['sms' => __('SMS'), 'whatsapp' => __('WhatsApp'), 'email' => __('E-mail')]" required col="col-12" />
                <x-form.input name="address" :label="__('Mobile number or e-mail')" required col="col-12" />
                <x-form.input name="reason" :label="__('Reason (optional)')" col="col-12" maxlength="191" />
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Add') }}</button></div>
        </form>
    </div>
    @endcan
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body border-bottom"><form method="GET"><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search number or e-mail') }}" aria-label="{{ __('Search number or e-mail') }}"></form></div>
            @if($optOuts->isEmpty())
                <x-empty-state icon="slash-circle" :title="__('Nobody has opted out')" />
            @else
                <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
                    <thead><tr><th>{{ __('Channel') }}</th><th>{{ __('Number / e-mail') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Since') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach($optOuts as $o)
                        <tr><td>{{ strtoupper($o->channel) }}</td><td>{{ $o->address }}</td><td>{{ $o->reason }}</td><td>{{ $o->created_at->format('d M Y') }}</td>
                            <td class="text-end">@can('notifications.configure')<form method="POST" action="{{ route('branch.communication.opt-outs.destroy', $o) }}" data-confirm="{{ __('Remove this opt-out? The contact will receive messages again.') }}">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></form>@endcan</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="card-body">{{ $optOuts->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
