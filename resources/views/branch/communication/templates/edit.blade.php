@extends('layouts.branch')
@section('title', __($config['label']))

@section('content')
<x-page-header :title="__($config['label'])" :breadcrumbs="[__('Message templates') => route('branch.communication.templates.index'), __($config['label']) => null]">
    <x-slot:actions>
        <form method="POST" action="{{ route('branch.communication.templates.reset', $event) }}" data-confirm="{{ __('Go back to the default wording for this notification?') }}">@csrf @method('DELETE')
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Restore defaults') }}</button></form>
    </x-slot:actions>
</x-page-header>

@if(session('warning'))<div class="alert alert-warning small">{{ session('warning') }}</div>@endif
<div class="alert alert-light border small">{{ __('Variables') }}: @foreach($config['vars'] as $v)<code class="me-1">{{ '{'.$v.'}' }}</code>@endforeach
    <div class="mt-1 text-body-secondary">{{ __('WhatsApp parameters, in order') }}: @foreach($config['whatsapp_params'] as $i => $v)<code class="me-1">&#123;&#123;{{ $i + 1 }}&#125;&#125; = {{ $v }}</code>@endforeach</div></div>

<form method="POST" action="{{ route('branch.communication.templates.update', $event) }}" class="row g-3">
    @csrf @method('PUT')
    @foreach(['bn' => 'বাংলা', 'en' => 'English'] as $locale => $title)
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold">{{ $title }}</div>
                <div class="card-body row g-2">
                    <div class="col-12">
                        <label class="form-label" for="{{ $locale }}_sms">{{ __('SMS text') }}</label>
                        <textarea id="{{ $locale }}_sms" name="{{ $locale }}[sms]" rows="3" maxlength="1000" class="form-control font-monospace small" data-sms-counter="{{ $locale }}_cnt">{{ old($locale.'.sms', $values[$locale]['sms']) }}</textarea>
                        <div class="form-text" id="{{ $locale }}_cnt" data-chars="{{ __('characters (before variables)') }}" data-parts="{{ __('SMS part(s)') }}"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="{{ $locale }}_subject">{{ __('E-mail / in-app subject') }}</label>
                        <input id="{{ $locale }}_subject" name="{{ $locale }}[subject]" value="{{ old($locale.'.subject', $values[$locale]['subject']) }}" maxlength="191" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="{{ $locale }}_body">{{ __('E-mail / in-app text') }}</label>
                        <textarea id="{{ $locale }}_body" name="{{ $locale }}[body]" rows="7" maxlength="5000" class="form-control small">{{ old($locale.'.body', $values[$locale]['body']) }}</textarea>
                    </div>
                    <div class="col-7">
                        <label class="form-label" for="{{ $locale }}_wa">{{ __('Approved WhatsApp template name') }}</label>
                        <input id="{{ $locale }}_wa" name="{{ $locale }}[whatsapp_template]" value="{{ old($locale.'.whatsapp_template', $values[$locale]['whatsapp_template']) }}" maxlength="100" pattern="[a-z0-9_]+" class="form-control @error($locale.'.whatsapp_template') is-invalid @enderror" placeholder="e.g. fee_receipt">
                        @error($locale.'.whatsapp_template')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-5">
                        <label class="form-label" for="{{ $locale }}_wal">{{ __('Template language code') }}</label>
                        <input id="{{ $locale }}_wal" name="{{ $locale }}[whatsapp_language]" value="{{ old($locale.'.whatsapp_language', $values[$locale]['whatsapp_language']) }}" maxlength="10" class="form-control" placeholder="{{ $locale === 'bn' ? 'bn' : 'en' }}">
                    </div>
                </div>
            </div>
        </div>
    @endforeach
    <div class="col-12"><button class="btn btn-brand">{{ __('Save templates') }}</button></div>
</form>
@endsection
