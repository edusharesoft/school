<?php

namespace App\Support;

/**
 * Taka amount in words using the South-Asian grouping (crore, lakh, thousand, hundred), e.g.
 * 125050.50 → "One lakh twenty-five thousand fifty taka and fifty paisa only". No intl extension needed.
 */
class AmountInWords
{
    private const ONES = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen',
        'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];

    private const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    public static function taka(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $taka = (int) floor($amount);
        $paisa = (int) round(($amount - $taka) * 100);

        $words = $taka === 0 ? 'zero' : self::number($taka);
        $out = $words.' taka';
        if ($paisa > 0) {
            $out .= ' and '.self::number($paisa).' paisa';
        }

        return ucfirst($out.' only');
    }

    public static function number(int $n): string
    {
        $parts = [];
        foreach ([[10000000, 'crore'], [100000, 'lakh'], [1000, 'thousand'], [100, 'hundred']] as [$size, $name]) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $parts[] = ($size === 10000000 && $count >= 100 ? self::number($count) : self::belowHundred($count)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = self::belowHundred($n);
        }

        return implode(' ', $parts);
    }

    private static function belowHundred(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        return self::TENS[intdiv($n, 10)].($n % 10 ? '-'.self::ONES[$n % 10] : '');
    }
}
