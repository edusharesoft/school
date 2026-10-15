@extends('layouts.branch')
@section('title', __('SMS, e-mail & WhatsApp settings'))

@php
    $labels = ['in_app' => __('In-app'), 'sms' => __('SMS'), 'email' => __('E-mail'), 'whatsapp' => __('WhatsApp')];
    $set = fn ($on) => $on ? '<span class="badge text-bg-success ms-1">'.e(__('saved')).'</span>' : '';
@endphp

@section('content')
<x-page-header :title="__('SMS, e-mail & WhatsApp settings')" :breadcrumbs="[__('Communication') => null, __('Settings') => null]" />

<form method="POST" action="{{ route('branch.communication.settings.update') }}" class="row g-3">
    @csrf @method('PUT')
    <div class="col-12">
        <div class="card"><div class="card-body row g-3">
            <x-form.switch name="sms_enabled" :label="__('Send SMS')" :checked="$values['sms_enabled']" col="col-md-3" />
            <x-form.switch name="email_enabled" :label="__('Send e-mail')" :checked="$values['email_enabled']" col="col-md-3" />
            <x-form.switch name="whatsapp_enabled" :label="__('Send WhatsApp')" :checked="$values['whatsapp_enabled']" col="col-md-3" />
            <x-form.select name="default_locale" :label="__('Language when the recipient has no portal account')" :value="$values['default_locale']" :options="['bn' => 'বাংলা', 'en' => 'English']" required col="col-md-3" />
        </div></div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-comment-sms me-1 text-primary"></i>{{ __('SMS gateway') }}</div>
            <div class="card-body row g-2">
                <x-form.select name="sms_driver" :label="__('Provider')" :value="$values['sms_driver']" required col="col-12"
                    :options="collect($drivers)->mapWithKeys(fn ($d, $k) => [$k => __($d['label'])])->all()" />
                <x-form.input name="sms[server]" :label="__('Server (Shataj SMS)')" :value="$values['sms_config']['server'] ?? null" placeholder="https://sms.shataj.com" col="col-md-6" />
                <x-form.input name="sms[device]" :label="__('Device ID (Shataj SMS, optional)')" :value="$values['sms_config']['device'] ?? null" placeholder="1 / 1|0" col="col-md-6"
                    :help="__('Empty = primary device. 1|0 = first SIM of device 1.')" />
                <x-form.input name="sms[sid]" :label="__('SID / sender (SSL Wireless)')" :value="$values['sms_config']['sid'] ?? null" col="col-md-6" />
                <x-form.input name="sms[sender_id]" :label="__('Sender ID (BulkSMSBD, Alpha SMS)')" :value="$values['sms_config']['sender_id'] ?? null" col="col-md-6" />
                <div class="col-md-6"><label class="form-label" for="sms_api_token">{{ __('API token (SSL Wireless)') }}{!! $set(in_array('api_token', $values['sms_secrets_set'], true)) !!}</label>
                    <input id="sms_api_token" name="sms[api_token]" type="password" autocomplete="new-password" class="form-control" placeholder="{{ __('leave empty to keep') }}"></div>
                <div class="col-md-6"><label class="form-label" for="sms_api_key">{{ __('API key (Shataj SMS, BulkSMSBD, Alpha SMS)') }}{!! $set(in_array('api_key', $values['sms_secrets_set'], true)) !!}</label>
                    <input id="sms_api_key" name="sms[api_key]" type="password" autocomplete="new-password" class="form-control" placeholder="{{ __('leave empty to keep') }}"></div>
                <div class="col-12 form-text">{{ __('Get the credentials and an approved sender ID / masking from your SMS provider. Many providers only accept requests from whitelisted server IP addresses. "Log only" writes messages to the application log and sends nothing.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-transparent fw-semibold"><i class="fa-solid fa-envelope me-1 text-success"></i>{{ __('E-mail') }}</div>
            <div class="card-body row g-2">
                <x-form.input name="mail_from_address" type="email" :label="__('From address')" :value="$values['mail_from_address']" col="col-md-6" />
                <x-form.input name="mail_from_name" :label="__('From name')" :value="$values['mail_from_name']" col="col-md-6" />
                <x-form.input name="smtp[host]" :label="__('SMTP server (empty = application mailer)')" :value="$values['smtp_config']['host'] ?? null" col="col-md-6" />
                <x-form.input name="smtp[port]" type="number" :label="__('Port')" :value="$values['smtp_config']['port'] ?? 587" col="col-md-3" />
                <x-form.select name="smtp[encryption]" :label="__('Encryption')" :value="$values['smtp_config']['encryption'] ?? 'tls'" :options="['tls' => 'TLS', 'ssl' => 'SSL', '' => __('None')]" col="col-md-3" />
                <x-form.input name="smtp[username]" :label="__('Username')" :value="$values['smtp_config']['username'] ?? null" col="col-md-6" />
                <div class="col-md-6"><label class="form-label" for="smtp_password">{{ __('Password') }}{!! $set($values['smtp_password_set']) !!}</label>
                    <input id="smtp_password" name="smtp[password]" type="password" autocomplete="new-password" class="form-control" placeholder="{{ __('leave empty to keep') }}"></div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header bg-transparent fw-semibold"><i class="fa-brands fa-whatsapp me-1 text-success"></i>{{ __('WhatsApp Business Platform (Cloud API)') }}</div>
            <div class="card-body row g-2">
                <x-form.input name="whatsapp[phone_number_id]" :label="__('Phone number ID')" :value="$values['whatsapp_config']['phone_number_id'] ?? null" col="col-md-3" />
                <x-form.input name="whatsapp[api_version]" :label="__('Graph API version')" :value="$values['whatsapp_config']['api_version'] ?? 'v23.0'" col="col-md-2" />
                <div class="col-md-3"><label class="form-label" for="wa_token">{{ __('Access token') }}{!! $set(in_array('access_token', $values['whatsapp_secrets_set'], true)) !!}</label>
                    <input id="wa_token" name="whatsapp[access_token]" type="password" autocomplete="new-password" class="form-control" placeholder="{{ __('leave empty to keep') }}"></div>
                <div class="col-md-2"><label class="form-label" for="wa_secret">{{ __('App secret') }}{!! $set(in_array('app_secret', $values['whatsapp_secrets_set'], true)) !!}</label>
                    <input id="wa_secret" name="whatsapp[app_secret]" type="password" autocomplete="new-password" class="form-control" placeholder="{{ __('leave empty to keep') }}"></div>
                <div class="col-md-2"><label class="form-label" for="wa_verify">{{ __('Webhook verify token') }}{!! $set(in_array('verify_token', $values['whatsapp_secrets_set'], true)) !!}</label>
                    <input id="wa_verify" name="whatsapp[verify_token]" type="password" autocomplete="new-password" class="form-control" placeholder="{{ __('leave empty to keep') }}"></div>
                <div class="col-12 form-text">
                    {{ __('Webhook URL for delivery reports (Meta app → WhatsApp → Configuration, subscribe to "messages"):') }} <code>{{ $webhookUrl }}</code><br>
                    {{ __('The school can only start conversations with templates approved by Meta. Create them in WhatsApp Manager, then enter each template name under Message templates. Recipients should have agreed to receive WhatsApp messages from the school.') }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header bg-transparent fw-semibold">{{ __('Which notifications go out on which channel') }}</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
                <thead class="table-light"><tr><th>{{ __('Notification') }}</th>@foreach($labels as $l)<th class="text-center">{{ $l }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach($events as $key => $e)
                    @continue($key === 'message.general' || $key === 'notice.published')
                    <tr><td>{{ __($e['label']) }}</td>
                        @foreach(array_keys($labels) as $ch)
                            <td class="text-center"><input type="checkbox" class="form-check-input" name="event_channels[{{ $key }}][]" value="{{ $ch }}" @checked(in_array($ch, $eventChannels[$key], true)) aria-label="{{ __($e['label']) }} — {{ $labels[$ch] }}"></td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="card-footer bg-transparent small text-body-secondary">{{ __('A channel that is switched off above is never used. Notices and bulk messages choose their channels when they are sent.') }}</div>
        </div>
    </div>

    <div class="col-12">
        <div class="card"><div class="card-body row g-3 align-items-end">
            <x-form.switch name="fee_reminder_enabled" :label="__('Send weekly reminders for overdue fees')" :checked="$values['fee_reminder_enabled']" col="col-md-6" />
            <x-form.input name="fee_reminder_days" type="number" min="0" max="60" :label="__('Days after the due date')" :value="$values['fee_reminder_days']" required col="col-md-3" />
        </div></div>
    </div>
    <div class="col-12"><button class="btn btn-brand">{{ __('Save settings') }}</button></div>
</form>

<form method="POST" action="{{ route('branch.communication.settings.test') }}" class="card mt-4" style="max-width: 720px">
    @csrf
    <div class="card-header bg-transparent fw-semibold">{{ __('Send a test message') }}</div>
    <div class="card-body row g-2 align-items-end">
        <x-form.select name="channel" :label="__('Channel')" :options="['sms' => __('SMS'), 'email' => __('E-mail')]" col="col-md-3" />
        <x-form.input name="to" :label="__('Mobile number or e-mail')" required col="col-md-6" />
        <div class="col-md-3"><button class="btn btn-outline-primary w-100">{{ __('Send test') }}</button></div>
        <div class="col-12 form-text">{{ __('Save the settings first. The test uses the saved provider even if the channel is switched off.') }}</div>
    </div>
</form>
@endsection
