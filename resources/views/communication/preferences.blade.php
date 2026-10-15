@extends('layouts.portal')
@section('title', __('Message preferences'))

@section('content')
<x-page-header :title="__('Message preferences')" :breadcrumbs="[__('Home') => route('home.redirect'), __('Message preferences') => null]" />

<form method="POST" action="{{ route('preferences.update') }}" class="card" style="max-width: 640px">
    @csrf @method('PUT')
    <div class="card-body">
        <p class="small text-body-secondary">{{ __('Choose how the school may send you routine messages (fees, attendance, results, notices). Emergency notices are always delivered. You always see everything in the portal.') }}</p>
        <x-form.switch name="sms" :label="__('SMS to :number', ['number' => $phone ?? '—'])" :checked="$phone && ! $optedOut->has('sms')" />
        <x-form.switch name="whatsapp" :label="__('WhatsApp to :number', ['number' => $phone ?? '—'])" :checked="$phone && ! $optedOut->has('whatsapp')" />
        <x-form.switch name="email" :label="__('E-mail to :address', ['address' => $email ?? '—'])" :checked="$email && ! $optedOut->has('email')" />
        @if(! $phone || ! $email)<div class="form-text mt-2">{{ __('To add or change a number or e-mail address, contact the school office.') }}</div>@endif
    </div>
    <div class="card-footer bg-transparent"><button class="btn btn-brand">{{ __('Save') }}</button></div>
</form>
@endsection
