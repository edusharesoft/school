<?php

namespace App\Services\Payments;

use App\Models\Finance\Account;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Payments\Gateways\AamarPayGateway;
use App\Services\Payments\Gateways\BanglaQrGateway;
use App\Services\Payments\Gateways\BkashGateway;
use App\Services\Payments\Gateways\NagadGateway;
use App\Services\Payments\Gateways\PaymentGateway;
use App\Services\Payments\Gateways\ShatajPayGateway;
use App\Services\Payments\Gateways\ShurjoPayGateway;
use App\Services\Payments\Gateways\SslCommerzGateway;
use App\Services\Settings\SettingsService;

/** Per-branch gateway settings (group "payments"); credentials marked secret are stored encrypted. */
class PaymentSettings
{
    public const GROUP = 'payments';

    public function __construct(protected SettingsService $settings) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings->get(self::GROUP, $key, $default);
    }

    public function set(string $key, mixed $value, bool $encrypt = false): void
    {
        $this->settings->set(self::GROUP, $key, $value, 'branch', $encrypt);
    }

    /** @return string[] enabled gateway keys, in catalogue order */
    public function enabled(): array
    {
        return array_values(array_filter(array_keys(config('payments.gateways')), fn ($g) => (bool) $this->get($g.'_enabled', false)
            && ($g !== 'banglaqr' || $this->qrText() !== null)));   // Bangla QR needs the bank's QR text before it can be offered
    }

    /** Gateways confirmed by people instead of a gateway API (Bangla QR). */
    public function isManual(string $gateway): bool
    {
        return (bool) config("payments.gateways.$gateway.manual", false);
    }

    /** The school's bank-issued Bangla QR text, or null when it is not set. */
    public function qrText(): ?string
    {
        $text = trim((string) (((array) $this->get('banglaqr_config', []))['qr_text'] ?? ''));

        return $text === '' ? null : $text;
    }

    public function isEnabled(string $gateway): bool
    {
        return in_array($gateway, $this->enabled(), true);
    }

    public function sandbox(string $gateway): bool
    {
        return (bool) $this->get($gateway.'_sandbox', true);
    }

    /** Merged configuration handed to the gateway: defaults, plain fields, secrets and the sandbox flag. */
    public function config(string $gateway): array
    {
        $defaults = collect(config("payments.gateways.$gateway.fields", []))->map(fn ($f) => $f['default'] ?? null)->all();

        return array_merge($defaults, array_filter((array) $this->get($gateway.'_config', []), fn ($v) => $v !== null && $v !== ''),
            (array) $this->get($gateway.'_secrets', []), ['sandbox' => $this->sandbox($gateway)]);
    }

    /** The cash / bank / mobile account that receives this gateway's settlements. */
    public function account(string $gateway): Account
    {
        $id = (int) $this->get($gateway.'_account_id', 0);
        $account = $id ? Account::query()->money()->find($id) : null;

        return $account ?? app(ChartOfAccounts::class)->get(config("payments.gateways.$gateway.account", 'bank'));
    }

    public function gateway(string $gateway): PaymentGateway
    {
        return match ($gateway) {
            'banglaqr' => app(BanglaQrGateway::class),
            'shatajpay' => app(ShatajPayGateway::class),
            'sslcommerz' => app(SslCommerzGateway::class),
            'bkash' => app(BkashGateway::class),
            'nagad' => app(NagadGateway::class),
            'shurjopay' => app(ShurjoPayGateway::class),
            'aamarpay' => app(AamarPayGateway::class),
            default => throw new \InvalidArgumentException("Unknown gateway [$gateway]."),
        };
    }

    public function formValues(): array
    {
        $out = [];
        foreach (config('payments.gateways') as $key => $def) {
            $out[$key] = [
                'enabled' => (bool) $this->get($key.'_enabled', false),
                'sandbox' => $this->sandbox($key),
                'account_id' => (int) $this->get($key.'_account_id', 0) ?: null,
                'config' => (array) $this->get($key.'_config', []),
                'secrets_set' => array_keys(array_filter((array) $this->get($key.'_secrets', []))),
            ];
        }

        return $out;
    }
}
