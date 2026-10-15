<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Secure cross-host sign-in used for branch switching.
 *
 * Sessions are host-only, so moving from central → branch (or branch → branch) needs a fresh
 * login on the target host. We issue a random single-use token (only its SHA-256 hash is stored,
 * in cache, for tenancy.handoff_ttl seconds) that the target host exchanges for a session.
 */
class HandoffService
{
    public function __construct(protected AuditLogger $audit) {}

    /** @param  Branch|null  $target  null = central area */
    public function issue(User $user, ?Branch $target): string
    {
        $token = Str::random(64);

        Cache::put($this->key($token), [
            'user_id' => $user->getKey(),
            'target' => $target ? 'branch:'.$target->getKey() : 'central',
        ], now()->addSeconds((int) config('tenancy.handoff_ttl', 60)));

        $this->audit->log('auth.handoff.issued', $target, [], ['target' => $target?->name ?? 'central'], userId: $user->getKey(),
            branchId: $target?->getKey(), institutionId: $target?->institution_id ?? $user->institution_id);

        return $target
            ? $target->url('auth/handoff/'.$token)
            : $this->centralUrl('auth/handoff/'.$token);
    }

    /**
     * Consume a token on the current host. Returns the user or null when the token is invalid,
     * expired, already used, or issued for another host.
     */
    public function consume(string $token, string $currentTargetKey): ?User
    {
        $payload = Cache::pull($this->key($token));

        if (! is_array($payload) || ($payload['target'] ?? null) !== $currentTargetKey) {
            return null;
        }

        return User::query()->find($payload['user_id']);
    }

    public function centralUrl(string $path = ''): string
    {
        $host = config('tenancy.central_domains')[0] ?? 'localhost';
        $scheme = config('tenancy.url_scheme') ?: (app()->runningInConsole() ? 'https' : request()->getScheme());
        $port = config('tenancy.url_port');
        if (! $port && ! app()->runningInConsole()) {
            $requestPort = request()->getPort();
            $port = in_array($requestPort, [80, 443], true) ? null : $requestPort;
        }

        return $scheme.'://'.$host.($port ? ':'.$port : '').'/'.ltrim($path, '/');
    }

    protected function key(string $token): string
    {
        return 'tenancy:handoff:'.hash('sha256', $token);
    }
}
