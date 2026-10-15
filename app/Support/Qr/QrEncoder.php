<?php

namespace App\Support\Qr;

use InvalidArgumentException;

/**
 * A small QR code encoder (ISO 18004): byte mode, error-correction level M, versions 1–20 (up to 666 bytes), mask chosen by the
 * penalty rules. It has no dependencies, so QR codes can be shown on web pages and in the app without any package.
 *
 *   QrEncoder::matrix('text')  → list of rows, each a list of bool (true = dark module), without the quiet zone
 *   QrEncoder::svg('text')     → an <svg> string with the 4-module quiet zone
 */
class QrEncoder
{
    /** version => [EC codewords per block, [[blocks, data codewords per block], …]] — level M */
    private const BLOCKS = [
        1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
        11 => [30, [[1, 50], [4, 51]]], 12 => [22, [[6, 36], [2, 37]]], 13 => [22, [[8, 37], [1, 38]]], 14 => [24, [[4, 40], [5, 41]]],
        15 => [24, [[5, 41], [5, 42]]], 16 => [28, [[7, 45], [3, 46]]], 17 => [28, [[10, 46], [1, 47]]], 18 => [26, [[9, 43], [4, 44]]],
        19 => [26, [[3, 44], [11, 45]]], 20 => [26, [[3, 41], [13, 42]]],
    ];

    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50], 11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62], 14 => [6, 26, 46, 66], 15 => [6, 26, 48, 70],
        16 => [6, 26, 50, 74], 17 => [6, 30, 54, 78], 18 => [6, 30, 56, 82], 19 => [6, 30, 58, 86], 20 => [6, 34, 62, 90],
    ];

    private static array $exp = [];

    private static array $log = [];

    /** @return list<list<bool>> */
    public static function matrix(string $text): array
    {
        $data = array_values(unpack('C*', $text) ?: []);
        $version = null;
        foreach (self::BLOCKS as $v => [$ec, $groups]) {
            $capacity = array_sum(array_map(fn ($g) => $g[0] * $g[1], $groups));
            $header = $v < 10 ? 12 : 20;                       // 4 mode bits + character count bits
            if (($header + 8 * count($data)) <= 8 * $capacity) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new InvalidArgumentException('The text is too long for a QR code of this encoder (max 666 bytes).');
        }

        $codewords = self::interleave($version, self::dataCodewords($version, $data));
        $size = 17 + 4 * $version;
        [$modules, $reserved] = self::functionPatterns($version, $size);
        self::placeData($modules, $reserved, $codewords, $size);

        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = self::applyMask($modules, $reserved, $size, $mask);
            self::placeFormat($candidate, $size, $mask);
            $score = self::penalty($candidate, $size);
            if ($score < $bestScore) {
                [$best, $bestScore] = [$candidate, $score];
            }
        }

        return array_map(fn ($row) => array_map(fn ($m) => $m === 1, $row), $best);
    }

    public static function svg(string $text, int $pixels = 280, string $dark = '#000000', string $light = '#ffffff'): string
    {
        $m = self::matrix($text);
        $n = count($m);
        $total = $n + 8;
        $path = '';
        foreach ($m as $y => $row) {
            $x = 0;
            while ($x < $n) {
                if ($row[$x]) {
                    $start = $x;
                    while ($x < $n && $row[$x]) {
                        $x++;
                    }
                    $path .= 'M'.($start + 4).','.($y + 4).'h'.($x - $start).'v1h-'.($x - $start).'z';
                } else {
                    $x++;
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$total.' '.$total.'" width="'.$pixels.'" height="'.$pixels.'" shape-rendering="crispEdges" role="img">'
            .'<rect width="'.$total.'" height="'.$total.'" fill="'.$light.'"/><path d="'.$path.'" fill="'.$dark.'"/></svg>';
    }

    // ------------------------------------------------------------------ data and error correction

    /** @return list<int> */
    private static function dataCodewords(int $version, array $bytes): array
    {
        $bits = '0100'.str_pad(decbin(count($bytes)), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach ($bytes as $b) {
            $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
        }
        $capacityBits = 8 * array_sum(array_map(fn ($g) => $g[0] * $g[1], self::BLOCKS[$version][1]));
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $words = array_map('bindec', str_split($bits, 8));
        for ($pad = 0; count($words) < $capacityBits / 8; $pad++) {
            $words[] = $pad % 2 === 0 ? 0xEC : 0x11;
        }

        return $words;
    }

    /** @return list<int> data and error-correction codewords in transmission order */
    private static function interleave(int $version, array $words): array
    {
        [$ecLen, $groups] = self::BLOCKS[$version];
        $dataBlocks = [];
        $ecBlocks = [];
        $pos = 0;
        foreach ($groups as [$count, $len]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($words, $pos, $len);
                $pos += $len;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::reedSolomon($block, $ecLen);
            }
        }
        $out = [];
        $max = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $max; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    private static function initField(): void
    {
        if (self::$exp) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
    }

    /** @return list<int> */
    private static function reedSolomon(array $data, int $ecLen): array
    {
        self::initField();
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= $coef === 0 ? 0 : self::$exp[self::$log[$coef] + $i];
            }
            $gen = $next;
        }
        $rem = array_fill(0, $ecLen, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($rem);
            $rem[] = 0;
            if ($factor !== 0) {
                foreach ($rem as $i => $_) {
                    $g = $gen[$i + 1];
                    $rem[$i] ^= $g === 0 ? 0 : self::$exp[self::$log[$g] + self::$log[$factor]];
                }
            }
        }

        return $rem;
    }

    // ------------------------------------------------------------------ the matrix

    /** @return array{0:array,1:array} modules (1 dark, 0 light) and the reserved map (function patterns, format and version areas) */
    private static function functionPatterns(int $version, int $size): array
    {
        $m = array_fill(0, $size, array_fill(0, $size, 0));
        $r = array_fill(0, $size, array_fill(0, $size, false));
        $set = function (int $x, int $y, int $v) use (&$m, &$r, $size) {
            if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
                $m[$y][$x] = $v;
                $r[$y][$x] = true;
            }
        };
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$ox, $oy]) {   // finder patterns with their separators
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $ring = max(abs($dx - 3), abs($dy - 3));
                    $set($ox + $dx, $oy + $dy, ($dx < 0 || $dy < 0 || $dx > 6 || $dy > 6) ? 0 : ($ring === 2 ? 0 : 1));
                }
            }
        }
        $pos = self::ALIGN[$version];
        foreach ($pos as $ay) {
            foreach ($pos as $ax) {
                if (($ax === 6 && $ay === 6) || ($ax === 6 && $ay === $pos[count($pos) - 1]) || ($ay === 6 && $ax === $pos[count($pos) - 1])) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $set($ax + $dx, $ay + $dy, max(abs($dx), abs($dy)) === 1 ? 0 : 1);
                    }
                }
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {                  // timing patterns
            $set($i, 6, $i % 2 === 0 ? 1 : 0);
            $set(6, $i, $i % 2 === 0 ? 1 : 0);
        }
        $set(8, $size - 8, 1);                                // the always-dark module
        for ($i = 0; $i < 9; $i++) {                          // reserve the format areas (filled per mask later)
            $set($i, 8, $m[8][$i] ?? 0);
            $set(8, $i, $m[$i][8] ?? 0);
        }
        for ($i = 0; $i < 8; $i++) {
            $set($size - 1 - $i, 8, 0);
            $set(8, $size - 1 - $i, 0);
        }
        $set(8, $size - 8, 1);
        if ($version >= 7) {
            $info = self::versionBits($version);
            for ($i = 0; $i < 18; $i++) {
                $bit = ($info >> $i) & 1;
                $set($size - 11 + $i % 3, intdiv($i, 3), $bit);
                $set(intdiv($i, 3), $size - 11 + $i % 3, $bit);
            }
        }

        return [$m, $r];
    }

    private static function placeData(array &$m, array $reserved, array $codewords, int $size): void
    {
        $bits = '';
        foreach ($codewords as $w) {
            $bits .= str_pad(decbin($w), 8, '0', STR_PAD_LEFT);
        }
        $i = 0;
        $n = strlen($bits);
        $upward = true;
        for ($x = $size - 1; $x >= 1; $x -= 2) {
            if ($x === 6) {
                $x = 5;                                   // the vertical timing column is skipped
            }
            for ($k = 0; $k < $size; $k++) {
                $y = $upward ? $size - 1 - $k : $k;
                foreach ([$x, $x - 1] as $xx) {
                    if (! $reserved[$y][$xx]) {
                        $m[$y][$xx] = ($i < $n && $bits[$i] === '1') ? 1 : 0;
                        $i++;
                    }
                }
            }
            $upward = ! $upward;
        }
    }

    private static function applyMask(array $m, array $reserved, int $size, int $mask): array
    {
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($reserved[$y][$x]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($flip) {
                    $m[$y][$x] ^= 1;
                }
            }
        }

        return $m;
    }

    private static function placeFormat(array &$m, int $size, int $mask): void
    {
        $data = (0b00 << 3) | $mask;                          // level M = 00
        $rem = $data << 10;
        for ($i = 4; $i >= 0; $i--) {
            if (($rem >> ($i + 10)) & 1) {
                $rem ^= 0x537 << $i;
            }
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        for ($i = 0; $i < 15; $i++) {
            $bit = ($bits >> $i) & 1;
            // first copy around the top-left finder
            if ($i < 6) {
                $m[$i][8] = $bit;
            } elseif ($i < 8) {
                $m[$i + 1][8] = $bit;
            } elseif ($i === 8) {
                $m[8][7] = $bit;
            } else {
                $m[8][14 - $i] = $bit;
            }
            // second copy: below the top-right and right of the bottom-left finder
            if ($i < 8) {
                $m[8][$size - 1 - $i] = $bit;
            } else {
                $m[$size - 15 + $i][8] = $bit;
            }
        }
        $m[$size - 8][8] = 1;
    }

    private static function versionBits(int $version): int
    {
        $rem = $version << 12;
        for ($i = 5; $i >= 0; $i--) {
            if (($rem >> ($i + 12)) & 1) {
                $rem ^= 0x1F25 << $i;
            }
        }

        return ($version << 12) | $rem;
    }

    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $size; $a++) {
                $run = 1;
                for ($b = 1; $b < $size; $b++) {
                    $cur = $pass ? $m[$b][$a] : $m[$a][$b];
                    $prev = $pass ? $m[$b - 1][$a] : $m[$a][$b - 1];
                    if ($cur === $prev) {
                        $run++;
                        if ($run === 5) {
                            $score += 3;
                        } elseif ($run > 5) {
                            $score++;
                        }
                    } else {
                        $run = 1;
                    }
                }
            }
        }
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $v = $m[$y][$x];
                if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $pattern = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $reverse = array_reverse($pattern);
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $size; $a++) {
                $line = [];
                for ($b = 0; $b < $size; $b++) {
                    $line[] = $pass ? $m[$b][$a] : $m[$a][$b];
                }
                for ($b = 0; $b <= $size - 11; $b++) {
                    $window = array_slice($line, $b, 11);
                    if ($window === $pattern || $window === $reverse) {
                        $score += 40;
                    }
                }
            }
        }
        $dark = 0;
        foreach ($m as $row) {
            $dark += array_sum($row);
        }
        $percent = 100 * $dark / ($size * $size);
        $score += 10 * (int) (abs($percent - 50) / 5);

        return $score;
    }
}
