<?php

namespace App\Services\Notifications;

use App\Services\Settings\SettingsService;

/**
 * Per-branch notification configuration (settings group "notifications"). Secrets — SMS API keys, SMTP password,
 * WhatsApp token and app secret — are stored encrypted and never sent back to the browser.
 */
class NotificationSettings
{
    public const GROUP = 'notifications';

    public const SECRET_FIELDS = ['api_token', 'api_key', 'password', 'access_token', 'app_secret'];

    public function __construct(protected SettingsService $settings) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings->get(self::GROUP, $key, $default);
    }

    public function set(string $key, mixed $value, bool $encrypt = false): void
    {
        $this->settings->set(self::GROUP, $key, $value, 'branch', $encrypt);
    }

    /** Channels switched on for the branch. In-app is always on. */
    public function enabledChannels(): array
    {
        return array_values(array_filter(config('notifications.channels'), fn ($c) => $c === 'in_app' || (bool) $this->get($c.'_enabled', false)));
    }

    public function channelEnabled(string $channel): bool
    {
        return in_array($channel, $this->enabledChannels(), true);
    }

    /** Channels chosen for an event (defaults from config/notifications.php). */
    public function eventChannels(string $event): array
    {
        $saved = $this->get('event_channels', []);

        return is_array($saved) && array_key_exists($event, $saved) ? (array) $saved[$event] : (array) EventCatalog::get($event, 'channels', []);
    }

    public function locale(): string
    {
        return in_array($this->get('default_locale', 'bn'), ['bn', 'en'], true) ? $this->get('default_locale', 'bn') : 'bn';
    }

    public function smsDriver(): string
    {
        $driver = (string) $this->get('sms_driver', 'log');

        return array_key_exists($driver, config('notifications.sms_drivers')) ? $driver : 'log';
    }

    public function smsConfig(): array
    {
        return array_merge((array) $this->get('sms_config', []), (array) $this->get('sms_secrets', []));
    }

    public function whatsappConfig(): array
    {
        return array_merge((array) $this->get('whatsapp_config', []), (array) $this->get('whatsapp_secrets', []));
    }

    /** SMTP settings of the branch, or null to use the application's mailer. */
    public function smtp(): ?array
    {
        $smtp = array_merge((array) $this->get('smtp_config', []), (array) $this->get('smtp_secrets', []));

        return filled($smtp['host'] ?? null) ? $smtp : null;
    }

    public function mailFrom(): array
    {
        return [
            'address' => $this->get('mail_from_address') ?: config('mail.from.address'),
            'name' => $this->get('mail_from_name') ?: (tenant()->branch()?->displayName() ?? config('mail.from.name')),
        ];
    }

    /** Values for the settings form (secrets reduced to "is set"). */
    public function formValues(): array
    {
        return [
            'sms_enabled' => (bool) $this->get('sms_enabled', false),
            'email_enabled' => (bool) $this->get('email_enabled', false),
            'whatsapp_enabled' => (bool) $this->get('whatsapp_enabled', false),
            'default_locale' => $this->locale(),
            'sms_driver' => $this->smsDriver(),
            'sms_config' => (array) $this->get('sms_config', []),
            'sms_secrets_set' => array_keys(array_filter((array) $this->get('sms_secrets', []))),
            'mail_from_address' => $this->get('mail_from_address'),
            'mail_from_name' => $this->get('mail_from_name'),
            'smtp_config' => (array) $this->get('smtp_config', []),
            'smtp_password_set' => filled($this->get('smtp_secrets', [])['password'] ?? null),
            'whatsapp_config' => (array) $this->get('whatsapp_config', []),
            'whatsapp_secrets_set' => array_keys(array_filter((array) $this->get('whatsapp_secrets', []))),
            'fee_reminder_enabled' => (bool) $this->get('fee_reminder_enabled', false),
            'fee_reminder_days' => (int) $this->get('fee_reminder_days', 3),
        ];
    }

    /** Merge new secret values into the stored ones; blank inputs keep what is stored. */
    public function saveSecrets(string $key, array $input, array $fields): void
    {
        $stored = (array) $this->get($key, []);
        foreach ($fields as $field) {
            if (filled($input[$field] ?? null)) {
                $stored[$field] = (string) $input[$field];
            }
        }
        $this->set($key, $stored, true);
    }
}
