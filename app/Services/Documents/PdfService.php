<?php

namespace App\Services\Documents;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Thin wrapper around mPDF so every document (certificate, ID card, admit card, mark sheet) is built the same way:
 * UTF-8, Bangla shaping, a private temp folder, and a clear message when the package is not installed.
 * Tests replace this class with a recorder, so no PDF library is needed to test the documents.
 */
class PdfService
{
    public function available(): bool
    {
        return class_exists(\Mpdf\Mpdf::class);
    }

    /** QR codes need the separate mpdf/qrcode package; without it the documents print the verification code as text only. */
    public function qrAvailable(): bool
    {
        return class_exists(\Mpdf\QrCode\QrCode::class);
    }

    /**
     * @param  array{format?:string|array{0:float,1:float}, orientation?:string, margins?:array{0:float,1:float,2:float,3:float}, title?:string, author?:string, watermark?:string, footer?:string}  $options  margins = top,right,bottom,left (mm)
     */
    public function render(string $html, array $options = []): string
    {
        if (! $this->available()) {
            throw new RuntimeException(__('PDF export needs the mPDF package. Run "composer require mpdf/mpdf" on the server.'));
        }

        $temp = (string) config('pdf.temp_dir');
        File::ensureDirectoryExists($temp);
        [$top, $right, $bottom, $left] = $options['margins'] ?? [10, 10, 10, 10];

        $defaults = (new \Mpdf\Config\ConfigVariables)->getDefaults();
        $fontDefaults = (new \Mpdf\Config\FontVariables)->getDefaults();
        $fontDirs = $defaults['fontDir'];
        $fontData = $fontDefaults['fontdata'];
        $family = (string) config('pdf.fallback_font', 'freeserif');

        if ($family === 'freeserif') {
            // FreeSerif has the Bangla glyphs but FreeSerifBold does not (bold Bangla would print as empty boxes), so the bundled family is
            // registered without a bold file: mPDF then draws bold by thickening the regular glyphs, for Bangla and Latin alike.
            $fontData['bnserif'] = ['R' => 'FreeSerif.ttf', 'I' => 'FreeSerifItalic.ttf', 'useOTL' => 0xFF, 'useKashida' => 75];
            $family = 'bnserif';
        }

        $custom = $this->customFont();
        if ($custom) {
            $fontDirs[] = $custom['dir'];
            $fontData['banglaui'] = ['R' => $custom['regular'], 'B' => $custom['bold'], 'I' => $custom['regular'], 'BI' => $custom['bold'], 'useOTL' => 0xFF, 'useKashida' => 75];
            $family = 'banglaui';
        }

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => $options['format'] ?? 'A4',
            'orientation' => $options['orientation'] ?? 'P',
            'margin_top' => $top, 'margin_right' => $right, 'margin_bottom' => $bottom, 'margin_left' => $left,
            'margin_header' => 0, 'margin_footer' => empty($options['footer']) ? 0 : 5,
            'tempDir' => $temp,
            'fontDir' => $fontDirs,
            'fontdata' => $fontData,
            'default_font' => $family,
            'autoScriptToLang' => false,
            'autoLangToFont' => false,
            'useSubstitutions' => true,
            'shrink_tables_to_fit' => 0,
            'img_dpi' => 150,
        ]);
        $mpdf->SetTitle((string) ($options['title'] ?? config('app.name')));
        $mpdf->SetAuthor((string) ($options['author'] ?? config('app.name')));
        $mpdf->SetCreator((string) config('app.name'));
        if (! empty($options['watermark'])) {
            $mpdf->SetWatermarkText((string) $options['watermark'], 0.12);
            $mpdf->showWatermarkText = true;
        }
        if (! empty($options['footer'])) {
            $mpdf->SetHTMLFooter((string) $options['footer']);   // may use {PAGENO} and {nbpg}
        }
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    }

    /** Stream a PDF to the browser (inline by default so it opens in a tab; $download forces a file download). */
    public function response(string $pdf, string $filename, bool $download = false): Response
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'document.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * A local image as a data: URI (mPDF reads it without a network or storage-link round trip), shrunk when GD is present.
     * With $ratio (width / height) the picture is cropped to that shape first, so a photo fills its frame instead of being squeezed;
     * the crop keeps the middle horizontally and sits a little above the middle vertically, where faces are.
     */
    public function image(?string $path, int $maxPx = 500, ?float $ratio = null): ?string
    {
        if (! $path || ! is_file($path) || filesize($path) === 0) {
            return null;
        }
        $bytes = (string) file_get_contents($path);
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return null;   // WebP / GIF / SVG are skipped (mPDF + GD support is not guaranteed)
        }
        [$sw, $sh] = [$info[0], $info[1]];
        [$cx, $cy, $cw, $ch] = [0, 0, $sw, $sh];
        if ($ratio !== null && $ratio > 0 && $sh > 0 && abs($sw / $sh - $ratio) > 0.02) {
            if ($sw / $sh > $ratio) {            // too wide: trim the sides
                $cw = (int) round($sh * $ratio);
                $cx = (int) floor(($sw - $cw) / 2);
            } else {                             // too tall: trim the bottom more than the top
                $ch = (int) round($sw / $ratio);
                $cy = (int) floor(($sh - $ch) * 0.3);
            }
        }
        $needsCrop = $cw !== $sw || $ch !== $sh;
        if (function_exists('imagecreatefromstring') && ($needsCrop || max($sw, $sh) > $maxPx)) {
            $src = @imagecreatefromstring($bytes);
            if ($src) {
                $scale = min(1, $maxPx / max($cw, $ch));
                $dst = imagecreatetruecolor(max(1, (int) round($cw * $scale)), max(1, (int) round($ch * $scale)));
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, $cx, $cy, imagesx($dst), imagesy($dst), $cw, $ch);
                ob_start();
                $info[2] === IMAGETYPE_PNG ? imagepng($dst) : imagejpeg($dst, null, 85);
                $bytes = (string) ob_get_clean();
            }
        }

        return 'data:'.$info['mime'].';base64,'.base64_encode($bytes);
    }

    /** @return array{dir:string,regular:string,bold:string}|null */
    protected function customFont(): ?array
    {
        $dir = (string) config('pdf.font_dir');
        $regular = (string) config('pdf.font.regular');
        if ($regular === '' || ! is_file($dir.DIRECTORY_SEPARATOR.$regular)) {
            return null;
        }
        $bold = (string) config('pdf.font.bold');

        return ['dir' => $dir, 'regular' => $regular, 'bold' => $bold !== '' && is_file($dir.DIRECTORY_SEPARATOR.$bold) ? $bold : $regular];
    }
}
