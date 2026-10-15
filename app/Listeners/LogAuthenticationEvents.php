<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/** Auto-discovered by Laravel (handle* methods). */
class LogAuthenticationEvents
{
    public function __construct(protected AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
            $this->audit->log('auth.login', $event->user, userId: $event->user->getKey());
        }
    }

    public function handleFailed(Failed $event): void
    {
        $login = $event->credentials['email'] ?? $event->credentials['phone'] ?? $event->credentials['username'] ?? null;
        $this->audit->log('auth.failed', null, [], ['login' => $login], userId: $event->user?->getAuthIdentifier());
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.logout', $event->user, userId: $event->user->getKey());
        }
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('auth.password-reset', $event->user, userId: $event->user->getKey());
        }
    }
}
