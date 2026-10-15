<?php

namespace App\Services\Notifications;

/**
 * Access to config('notifications.events'). Event keys contain a dot ("fee.invoice"), so they must never be put into a
 * dotted config path — config("notifications.events.fee.invoice") would look for events → fee → invoice.
 */
final class EventCatalog
{
    public static function all(): array
    {
        return (array) config('notifications.events', []);
    }

    public static function exists(string $event): bool
    {
        return array_key_exists($event, self::all());
    }

    /** One event's definition, or a value inside it ("channels", "templates.bn.sms" …). */
    public static function get(string $event, ?string $key = null, mixed $default = null): mixed
    {
        $definition = self::all()[$event] ?? null;
        if ($definition === null) {
            return $default;
        }

        return $key === null ? $definition : data_get($definition, $key, $default);
    }

    public static function label(string $event): string
    {
        return (string) self::get($event, 'label', $event);
    }
}
