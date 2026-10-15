<?php

namespace App\Support;

/**
 * Code 128 (code set B) barcode rendered as an inline SVG — no image library or font needed, so labels print
 * crisply and scan from the screen too. Accepts printable ASCII (32–126), which covers accession numbers.
 */
class Code128
{
    /** Bar/space widths of the 107 symbols (0–102 data, 103–105 start A/B/C, 106 stop). */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /** The symbol values (start, data…, checksum, stop). @return int[] */
    public static function symbols(string $text): array
    {
        $values = [self::START_B];
        foreach (str_split($text) as $char) {
            $code = ord($char);
            if ($code < 32 || $code > 126) {
                throw new \InvalidArgumentException('Code 128 (set B) supports printable ASCII only.');
            }
            $values[] = $code - 32;
        }
        $sum = self::START_B;
        foreach (array_slice($values, 1) as $i => $v) {
            $sum += $v * ($i + 1);
        }
        $values[] = $sum % 103;
        $values[] = self::STOP;

        return $values;
    }

    /** Bar widths in modules, bars at even offsets. @return int[] */
    public static function modules(string $text): array
    {
        $out = [];
        foreach (self::symbols($text) as $symbol) {
            foreach (str_split(self::PATTERNS[$symbol]) as $w) {
                $out[] = (int) $w;
            }
        }

        return $out;
    }

    public static function svg(string $text, int $height = 40, int $module = 2, bool $caption = true): string
    {
        $widths = self::modules($text);
        $quiet = 10 * $module;
        $x = $quiet;
        $bars = '';
        foreach ($widths as $i => $w) {
            if ($i % 2 === 0) {
                $bars .= sprintf('<rect x="%d" y="0" width="%d" height="%d"/>', $x, $w * $module, $height);
            }
            $x += $w * $module;
        }
        $width = $x + $quiet;
        $total = $height + ($caption ? 14 : 0);
        $label = $caption
            ? sprintf('<text x="%s" y="%d" font-size="11" text-anchor="middle" font-family="monospace" fill="#000">%s</text>', $width / 2, $height + 12, htmlspecialchars($text, ENT_QUOTES))
            : '';

        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img" aria-label="%s"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g>%s</svg>',
            $width, $total, $width, $total, htmlspecialchars('Barcode '.$text, ENT_QUOTES), $bars, $label);
    }
}
