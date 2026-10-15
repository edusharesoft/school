@extends('layouts.branch')
@section('title', __('Online payment settings'))

@section('content')
<x-page-header :title="__('Online payment settings')" :breadcrumbs="[__('Fees & accounts') => route('branch.finance.invoices.index'), __('Online payments') => route('branch.finance.online-payments.index'), __('Settings') => null]" />

<div class="alert alert-info small" style="max-width: 980px">
    <div class="fw-semibold mb-1"><i class="fa-solid fa-circle-info me-1"></i>{{ __('How it works') }}</div>
    {{ __('Parents and students pay from the Fees page of their portal. The school records a payment only after the gateway confirms it server-to-server; a success page in the browser is never trusted on its own. Unconfirmed attempts are checked again every ten minutes.') }}
    <div class="mt-2">{{ __('SSLCOMMERZ IPN URL (enter it in the merchant panel)') }}: <code class="user-select-all">{{ $ipnUrl }}</code></div>
    <div>{{ __('Use sandbox mode with the test credentials from each provider until the live account is approved. Secrets are stored encrypted and are never shown again; leave a secret empty to keep the saved one.') }}</div>
</div>

<form method="POST" action="{{ route('branch.finance.online-payments.settings.save') }}" style="max-width: 980px">
    @csrf @method('PUT')
    @foreach($gateways as $key => $def)
        @php($v = $values[$key])
        <div class="card mb-3" id="gw-{{ $key }}">
            <div class="card-header bg-transparent d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-semibold">{{ $def['label'] }}</span>
                @if($v['enabled'])<span class="badge text-bg-success">{{ __('Enabled') }}</span>@endif
                @if($v['enabled'] && $v['sandbox'])<span class="badge text-bg-warning">{{ __('Sandbox') }}</span>@endif
                @unless($def['refund'] ?? false)<span class="badge text-bg-light border">{{ __('Refunds from the merchant panel') }}</span>@endunless
            </div>
            <div class="card-body row g-3">
                <x-form.switch :name="$key.'[enabled]'" :label="__('Offer :gateway to parents and students', ['gateway' => $def['short']])" :checked="$v['enabled']" col="col-md-6" />
                @if(! ($def['manual'] ?? false))
                    <x-form.switch :name="$key.'[sandbox]'" :label="__('Sandbox (test) mode')" :checked="$v['sandbox']" col="col-md-6" />
                @endif
                @foreach($def['fields'] as $field => $f)
                    @php($secretSet = in_array($field, $v['secrets_set'], true))
                    @php($label = __($f['label']).(($f['secret'] ?? false) && $secretSet ? ' — '.__('saved') : ''))
                    @php($placeholder = ($f['secret'] ?? false) ? ($secretSet ? __('•••••• (unchanged)') : __('Not set')) : ($f['default'] ?? ''))
                    @if($f['textarea'] ?? false)
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $key }}_{{ $field }}">{{ $label }}</label>
                            <textarea id="{{ $key }}_{{ $field }}" name="{{ $key }}[{{ $field }}]" rows="4" class="form-control font-monospace small @error($key.'.'.$field) is-invalid @enderror"
                                placeholder="{{ $placeholder }}" autocomplete="off" spellcheck="false">{{ ($f['secret'] ?? false) ? '' : old($key.'.'.$field, $v['config'][$field] ?? '') }}</textarea>
                            @error($key.'.'.$field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @elseif($f['secret'] ?? false)
                        {{-- Never re-filled from old input: a secret must not travel back to the browser. --}}
                        <div class="col-md-6">
                            <label class="form-label" for="{{ $key }}_{{ $field }}">{{ $label }}</label>
                            <input id="{{ $key }}_{{ $field }}" type="password" name="{{ $key }}[{{ $field }}]" value="" placeholder="{{ $placeholder }}" autocomplete="new-password"
                                class="form-control @error($key.'.'.$field) is-invalid @enderror">
                            @error($key.'.'.$field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <x-form.input :name="$key.'['.$field.']'" :label="$label" :value="$v['config'][$field] ?? ''" :placeholder="$placeholder" autocomplete="off" col="col-md-6" />
                    @endif
                @endforeach
                <x-form.select :name="$key.'[account_id]'" :label="__('Deposit account')" :options="$accounts" :value="$v['account_id']" col="col-md-6"
                    :placeholder="$def['account'] === 'mobile' ? __('Default: mobile wallet account') : __('Default: bank account')"
                    :help="__('Where the collected money is posted in the books until the settlement reaches your bank.')" />
                @if($key === 'banglaqr')
                    <div class="col-12 form-text">
                        {{ __('Bangla QR is the national QR of Bangladesh Bank. Ask your bank or mobile-wallet provider for a merchant (static) Bangla QR for the school account, scan it with a phone, and paste the text it contains (it starts with 000201). The system adds the exact amount and the payment reference for every bill, so payers never type an amount.') }}
                        @if($qrInfo)
                            <div class="mt-1 text-success"><i class="fa-solid fa-circle-check me-1"></i>{{ __('Saved QR: :name, :city', ['name' => $qrInfo['name'] ?: '—', 'city' => $qrInfo['city'] ?: '—']) }}</div>
                        @endif
                        <div class="mt-1">{{ __('Payments are confirmed by the accounts office, or automatically when you upload the bank statement under Online payments → Match a bank statement.') }}</div>
                    </div>
                @endif
                @if($key === 'shatajpay')
                    <div class="col-12 form-text">
                        {{ __('Set this callback URL for the merchant app in the ShatajPay panel') }}: <code class="user-select-all">{{ $shatajCallbackUrl }}</code><br>
                        {{ __('ShatajPay does not offer a verification API yet, so a payment it reports as complete waits under Online payments until the accounts office confirms it in the ShatajPay panel and accepts it.') }}
                    </div>
                @endif
                @if($key === 'nagad')
                    <div class="col-12 form-text">{{ __('Nagad only accepts requests from the server IP registered with the merchant account; enter that IP above. Keys are the base64 texts Nagad provides (with or without PEM header lines).') }}</div>
                @endif
                @if($key === 'bkash')
                    <div class="col-12 form-text">{{ __('bKash Tokenized Checkout: the callback URL is sent with every payment, nothing has to be registered in advance.') }}</div>
                @endif
            </div>
        </div>
    @endforeach
    <x-form.actions :cancel="route('branch.finance.online-payments.index')" />
</form>
@endsection
