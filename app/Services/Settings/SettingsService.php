<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Hierarchical key/value settings: branch → institution → platform → default.
 * Secrets (gateway keys, SMS passwords) are stored with $encrypt = true.
 */
class SettingsService
{
    public function __construct(protected TenantContext $tenant) {}

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        foreach ($this->scopeChain() as $scopeKey) {
            $all = $this->load($scopeKey);
            if (array_key_exists("{$group}.{$key}", $all)) {
                [$encrypted, $value] = $all["{$group}.{$key}"];

                // Secrets stay encrypted inside the cache; decrypt only at the point of use.
                return $encrypted && is_string($value) ? json_decode(Crypt::decryptString($value), true) : $value;
            }
        }

        return $default;
    }

    /** Read a platform-level setting only (ignores branch / institution overrides). */
    public function platform(string $group, string $key, mixed $default = null): mixed
    {
        $all = $this->load('platform');
        if (! array_key_exists("{$group}.{$key}", $all)) {
            return $default;
        }
        [$encrypted, $value] = $all["{$group}.{$key}"];

        return $encrypted && is_string($value) ? json_decode(Crypt::decryptString($value), true) : $value;
    }

    /** Read the value stored exactly at one level (no fallback) — used by settings forms. */
    public function at(string $level, string $group, string $key, mixed $default = null): mixed
    {
        try {
            [$scopeKey] = $this->scopeFor($level);
        } catch (\InvalidArgumentException) {
            return $default;
        }
        $all = $this->load($scopeKey);
        if (! array_key_exists("{$group}.{$key}", $all)) {
            return $default;
        }
        [$encrypted, $value] = $all["{$group}.{$key}"];

        return $encrypted && is_string($value) ? json_decode(Crypt::decryptString($value), true) : $value;
    }

    public function forget(string $group, string $key, string $level = 'branch'): void
    {
        [$scopeKey] = $this->scopeFor($level);
        SystemSetting::query()->where(['scope_key' => $scopeKey, 'group' => $group, 'key' => $key])->delete();
        Cache::forget($this->cacheKey($scopeKey));
    }

    /** Write a setting at the current branch (default), institution or platform scope. */
    public function set(string $group, string $key, mixed $value, string $level = 'branch', bool $encrypt = false): void
    {
        [$scopeKey, $institutionId, $branchId] = $this->scopeFor($level);

        SystemSetting::query()->updateOrCreate(
            ['scope_key' => $scopeKey, 'group' => $group, 'key' => $key],
            [
                'institution_id' => $institutionId,
                'branch_id' => $branchId,
                'value' => ['v' => $encrypt ? Crypt::encryptString(json_encode($value)) : $value],
                'is_encrypted' => $encrypt,
            ]
        );

        Cache::forget($this->cacheKey($scopeKey));
    }

    /** @return string[] */
    protected function scopeChain(): array
    {
        $chain = [];
        if ($this->tenant->branchId()) {
            $chain[] = 'b:'.$this->tenant->branchId();
        }
        if ($this->tenant->institutionId()) {
            $chain[] = 'i:'.$this->tenant->institutionId();
        }
        $chain[] = 'platform';

        return $chain;
    }

    /** @return array{0:string,1:?int,2:?int} */
    protected function scopeFor(string $level): array
    {
        return match ($level) {
            'branch' => $this->tenant->branchId()
                ? ['b:'.$this->tenant->branchId(), $this->tenant->institutionId(), $this->tenant->branchId()]
                : throw new \InvalidArgumentException('No branch in context.'),
            'institution' => $this->tenant->institutionId()
                ? ['i:'.$this->tenant->institutionId(), $this->tenant->institutionId(), null]
                : throw new \InvalidArgumentException('No institution in context.'),
            default => ['platform', null, null],
        };
    }

    /** @return array<string,array{0:bool,1:mixed}> */
    protected function load(string $scopeKey): array
    {
        return Cache::remember($this->cacheKey($scopeKey), 3600, function () use ($scopeKey) {
            $out = [];
            foreach (SystemSetting::query()->where('scope_key', $scopeKey)->get() as $row) {
                $out[$row->group.'.'.$row->key] = [(bool) $row->is_encrypted, $row->value['v'] ?? null];
            }

            return $out;
        });
    }

    protected function cacheKey(string $scopeKey): string
    {
        return 'settings:'.$scopeKey;
    }
}
