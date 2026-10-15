<?php

namespace App\Services\Notifications;

/**
 * SMS parts: GSM-7 text 160 characters (153 per part when split); anything else — Bangla included — is sent as
 * UCS-2: 70 characters (67 per part). Providers bill per part.
 */
final class SmsCounter
{
    public static function isUnicode(string $text): bool
    {
        return (bool) preg_match('/[^\x{0000}-\x{007F}£¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ¡ÄÖÑÜ§¿äöñüà€]/u', $text);
    }

    public static function length(string $text): int
    {
        if (self::isUnicode($text)) {
            return mb_strlen($text, 'UTF-8');
        }
        // ^ { } \ [ ~ ] | € take two characters in GSM-7.
        return mb_strlen($text, 'UTF-8') + preg_match_all('/[\^{}\\\\\[~\]|€]/u', $text);
    }

    public static function segments(string $text): int
    {
        $length = self::length($text);
        if ($length === 0) {
            return 0;
        }
        [$single, $multi] = self::isUnicode($text) ? [70, 67] : [160, 153];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}
