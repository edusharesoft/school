<?php

namespace App\Services\Payments\BanglaQr;

use InvalidArgumentException;

/**
 * Bangla QR is the Bangladesh Bank national QR standard, built on the EMVCo merchant-presented QR (tag–length–value text, closed by
 * a CRC-16). The merchant account information (tags 26–51) is issued by the school's bank or mobile-wallet provider, so the school
 * pastes the text of the static QR it received; this class checks it and turns it into a dynamic QR for one bill:
 * point of initiation "12", the amount (tag 54) and the bill number / reference (tag 62), with the CRC calculated again.
 * Everything else (account templates, category code, name, city, language template …) is carried over unchanged.
 *
 *   000201 010211 2633… 52 04… 5303050 5802BD 59… 60… 6304CRC
 */
class EmvQr
{
    /** @return list<array{0:string,1:string}> the tags in their original order */
    public static function parse(string $payload): array
    {
        $tags = [];
        $pos = 0;
        $length = strlen($payload);
        while ($pos < $length) {
            if ($pos + 4 > $length || ! ctype_digit(substr($payload, $pos, 4))) {
                throw new InvalidArgumentException(__('This is not a valid QR text: a tag or its length is damaged at position :pos.', ['pos' => $pos]));
            }
            $id = substr($payload, $pos, 2);
            $len = (int) substr($payload, $pos + 2, 2);
            if ($pos + 4 + $len > $length) {
                throw new InvalidArgumentException(__('This is not a valid QR text: tag :id is longer than the text.', ['id' => $id]));
            }
            $tags[] = [$id, substr($payload, $pos + 4, $len)];
            $pos += 4 + $len;
        }

        return $tags;
    }

    /** CRC-16/CCITT-FALSE (polynomial 0x1021, start 0xFFFF) as four upper-case hex digits. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        foreach (unpack('C*', $data) ?: [] as $byte) {
            $crc ^= $byte << 8;
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Checks a pasted QR text and returns what a person needs to recognise it.
     *
     * @return array{name:string,city:string,category:?string,currency:?string,country:?string,account_tags:list<string>,has_amount:bool}
     */
    public static function inspect(string $payload): array
    {
        $payload = trim($payload);
        if (strlen($payload) < 40 || strlen($payload) > 600) {
            throw new InvalidArgumentException(__('This does not look like a Bangla QR text (it is too short or too long).'));
        }
        $tags = self::parse($payload);
        $map = [];
        foreach ($tags as [$id, $value]) {
            $map[$id] = $value;
        }
        if (($tags[0][0] ?? null) !== '00' || ($map['00'] ?? null) !== '01') {
            throw new InvalidArgumentException(__('The QR text must start with 000201 (EMV payload format 01).'));
        }
        if (($tags[count($tags) - 1][0] ?? null) !== '63' || strlen($map['63'] ?? '') !== 4) {
            throw new InvalidArgumentException(__('The QR text must end with the CRC tag (6304….).'));
        }
        $body = substr($payload, 0, -4);
        if (strtoupper(substr($payload, -4)) !== self::crc16($body)) {
            throw new InvalidArgumentException(__('The check code (CRC) of this QR text is wrong. Copy the whole text again, without spaces or line breaks added.'));
        }
        $accountTags = array_values(array_map('strval', array_filter(array_keys($map), fn ($id) => (int) $id >= 2 && (int) $id <= 51)));
        if ($accountTags === []) {
            throw new InvalidArgumentException(__('The QR text has no merchant account (tags 02–51). Use the QR your bank or wallet gave you for receiving payments.'));
        }
        if (isset($map['53']) && $map['53'] !== '050') {
            throw new InvalidArgumentException(__('The QR text is not in Bangladeshi taka (currency code 050).'));
        }

        return [
            'name' => $map['59'] ?? '', 'city' => $map['60'] ?? '', 'category' => $map['52'] ?? null, 'currency' => $map['53'] ?? null,
            'country' => $map['58'] ?? null, 'account_tags' => $accountTags, 'has_amount' => isset($map['54']),
        ];
    }

    /**
     * The QR text for one bill, built from the school's static QR.
     *
     * @param  string  $bill  bill number (tag 62, sub-tag 01), at most 25 letters / digits — we use our payment reference
     * @param  string|null  $reference  reference label (tag 62, sub-tag 05), at most 25 characters — we use the student ID
     */
    public static function dynamic(string $static, float $amount, string $bill, ?string $reference = null): string
    {
        self::inspect($static);
        if ($amount <= 0 || $amount > 9999999999.99) {
            throw new InvalidArgumentException(__('The amount for the QR code is not valid.'));
        }
        $map = [];
        foreach (self::parse(trim($static)) as [$id, $value]) {
            $map[$id] = $value;
        }
        $map['01'] = '12';                                              // dynamic: one amount, one use
        $map['53'] = '050';
        $map['54'] = number_format($amount, 2, '.', '');

        $extra = isset($map['62']) ? array_column(self::parse($map['62']), 1, 0) : [];
        $extra['01'] = substr(preg_replace('/[^A-Za-z0-9]/', '', $bill) ?? '', 0, 25);
        if ($reference !== null && trim($reference) !== '') {
            $extra['05'] = substr(preg_replace('/[^A-Za-z0-9 \-_.\/]/', '', $reference) ?? '', 0, 25);
        }
        ksort($extra, SORT_STRING);
        $map['62'] = self::build($extra);
        unset($map['63']);
        ksort($map, SORT_STRING);

        $text = self::build($map).'6304';

        return $text.self::crc16($text);
    }

    /** @param array<string,string> $tags */
    private static function build(array $tags): string
    {
        $out = '';
        foreach ($tags as $id => $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $out .= str_pad((string) $id, 2, '0', STR_PAD_LEFT).str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
        }

        return $out;
    }
}
