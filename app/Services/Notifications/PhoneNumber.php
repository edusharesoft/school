<?php

namespace App\Services\Notifications;

/** Bangladeshi mobile numbers: accepts 01XXXXXXXXX, 8801…, +880 1…, with spaces / dashes; returns 8801XXXXXXXXX or null. */
final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            $digits = '88'.$digits;
        } elseif (str_starts_with($digits, '1') && strlen($digits) === 10) {
            $digits = '880'.$digits;
        }

        return preg_match('/^8801[3-9]\d{8}$/', $digits) ? $digits : null;
    }
}
