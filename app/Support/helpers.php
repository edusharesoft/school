<?php

use App\Models\Branch;
use App\Services\Settings\SettingsService;
use App\Tenancy\TenantContext;

if (! function_exists('tenant')) {
    /** The active tenant context (branch / central / none). */
    function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }
}

if (! function_exists('current_branch')) {
    function current_branch(): ?Branch
    {
        return app(TenantContext::class)->branch();
    }
}

if (! function_exists('setting')) {
    /** Read a setting for the current tenant, falling back branch → institution → platform → default. */
    function setting(string $group, string $key, mixed $default = null): mixed
    {
        return app(SettingsService::class)->get($group, $key, $default);
    }
}

if (! function_exists('bn_digits')) {
    /** Convert ASCII digits to Bangla digits when the active locale is bn. */
    function bn_digits(string|int|float|null $value, bool $force = false): string
    {
        $value = (string) $value;
        if (! $force && app()->getLocale() !== 'bn') {
            return $value;
        }

        return strtr($value, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}

if (! function_exists('money_bdt')) {
    /** Format an amount using the South-Asian grouping used in Bangladesh (e.g. 12,34,567.00). */
    function money_bdt(int|float|string|null $amount, bool $symbol = true): string
    {
        $amount = (float) $amount;
        $negative = $amount < 0;
        [$int, $dec] = explode('.', number_format(abs($amount), 2, '.', ''));

        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $rest = $rest !== '' ? preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).',' : '';
        $formatted = ($negative ? '-' : '').$rest.$last3.'.'.$dec;

        return bn_digits(($symbol ? '৳ ' : '').$formatted);
    }
}
