<?php

namespace App\Services\Reports\Exporters;

use App\Services\Reports\ReportResult;
use RuntimeException;
use ZipArchive;

/**
 * Writes a one-sheet .xlsx (Office Open XML) with PHP's ZipArchive only — no spreadsheet library is needed.
 * Layout: title, subtitle, filters, a blank row, a bold header (filter + frozen), the data, a bold totals row.
 * Text is written as inline strings (never as formulas), numbers and dates as real numbers with number formats.
 */
class XlsxWriter
{
    // cellXfs indexes of styles()
    private const S_DEFAULT = 0;

    private const S_TITLE = 1;

    private const S_HEADER = 2;

    private const S_MONEY = 3;

    private const S_INT = 4;

    private const S_DATE = 5;

    private const S_PERCENT = 6;

    private const S_DATETIME = 7;

    private const S_MUTED = 8;

    private const S_TOTAL_TEXT = 9;

    private const S_TOTAL_MONEY = 10;

    private const S_TOTAL_INT = 11;

    private const S_HEADER_RIGHT = 12;

    private const S_TEXT_BOLD = 13;

    public function toFile(ReportResult $result, string $path, ?string $generatedBy = null): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException(__('Excel export needs the PHP zip extension.'));
        }

        $sheet = tempnam(sys_get_temp_dir(), 'xl');
        $this->writeSheet($result, $sheet, $generatedBy);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sheet);
            throw new RuntimeException('Cannot create the Excel file.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.$this->esc($result->title).'</dc:title><dc:creator>'.$this->esc((string) config('app.name')).'</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$this->esc($this->sheetName($result->title)).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($sheet);
    }

    protected function writeSheet(ReportResult $result, string $file, ?string $generatedBy): void
    {
        $out = fopen($file, 'wb');
        $columns = $result->columns;
        $n = max(count($columns), 1);
        $row = 0;

        // header lines above the table
        $top = [[$result->title, self::S_TITLE]];
        if ($result->subtitle) {
            $top[] = [$result->subtitle, self::S_MUTED];
        }
        foreach ($result->filters as $label => $value) {
            $top[] = [$label.': '.$value, self::S_MUTED];
        }
        foreach ($result->summary as $label => $value) {
            $top[] = [$label.': '.(is_numeric($value) ? number_format((float) $value, floor((float) $value) == (float) $value ? 0 : 2) : $value), self::S_TEXT_BOLD];
        }
        $top[] = [__('Generated').': '.now()->format('d/m/Y H:i').($generatedBy ? ' · '.$generatedBy : ''), self::S_MUTED];

        // column widths from the longest text
        $widths = [];
        foreach ($columns as $i => $column) {
            $w = mb_strlen($column['label']);
            foreach (array_slice($result->rows, 0, 400) as $r) {
                $w = max($w, mb_strlen(ReportResult::display($r[$column['key']] ?? null, $column['type'])));
            }
            $widths[$i] = min(max($w + 2, 8), 50);
        }

        $headerRow = count($top) + 2;       // one blank row between the notes and the table
        $lastDataRow = $headerRow + count($result->rows);
        $totalRow = $result->hasTotals() ? $lastDataRow + 1 : null;
        $last = $this->col($n - 1);

        fwrite($out, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        fwrite($out, '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0" showGridLines="1"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        fwrite($out, '<sheetFormatPr defaultRowHeight="15"/><cols>');
        foreach ($widths as $i => $w) {
            fwrite($out, '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>');
        }
        fwrite($out, '</cols><sheetData>');

        foreach ($top as [$text, $style]) {
            $row++;
            fwrite($out, '<row r="'.$row.'">'.$this->text('A'.$row, (string) $text, $style).'</row>');
        }
        $row++;   // blank
        $row++;
        $cells = '';
        foreach ($columns as $i => $column) {
            $cells .= $this->text($this->col($i).$row, $column['label'], $result->isNumeric($column) ? self::S_HEADER_RIGHT : self::S_HEADER);
        }
        fwrite($out, '<row r="'.$row.'">'.$cells.'</row>');

        foreach ($result->rows as $data) {
            $row++;
            $cells = '';
            foreach ($columns as $i => $column) {
                $cells .= $this->cell($this->col($i).$row, $data[$column['key']] ?? null, $column['type']);
            }
            fwrite($out, '<row r="'.$row.'">'.$cells.'</row>');
        }

        if ($totalRow !== null) {
            $row++;
            $totals = $result->totals();
            $cells = '';
            foreach ($columns as $i => $column) {
                $ref = $this->col($i).$row;
                if (isset($totals[$column['key']])) {
                    $cells .= '<c r="'.$ref.'" s="'.($column['type'] === 'int' ? self::S_TOTAL_INT : self::S_TOTAL_MONEY).'"><v>'.$totals[$column['key']].'</v></c>';
                } else {
                    $cells .= $this->text($ref, $i === 0 ? (string) __('Total') : '', self::S_TOTAL_TEXT);
                }
            }
            fwrite($out, '<row r="'.$row.'">'.$cells.'</row>');
        }

        fwrite($out, '</sheetData>');
        if ($result->rows !== []) {
            fwrite($out, '<autoFilter ref="A'.$headerRow.':'.$last.$lastDataRow.'"/>');
        }
        fwrite($out, '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/><pageSetup orientation="'.($n > 6 ? 'landscape' : 'portrait').'" fitToWidth="1" fitToHeight="0"/></worksheet>');
        fclose($out);
    }

    protected function cell(string $ref, mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        switch ($type) {
            case 'int':
                return '<c r="'.$ref.'" s="'.self::S_INT.'"><v>'.(int) round((float) $value).'</v></c>';
            case 'number':
            case 'money':
                return '<c r="'.$ref.'" s="'.self::S_MONEY.'"><v>'.round((float) $value, 2).'</v></c>';
            case 'percent':
                return '<c r="'.$ref.'" s="'.self::S_PERCENT.'"><v>'.round((float) $value, 1).'</v></c>';
            case 'date':
            case 'datetime':
                $date = ReportResult::carbon($value);
                if (! $date) {
                    return $this->text($ref, (string) $value, self::S_DEFAULT);
                }
                $serial = $this->serial($date, $type === 'datetime');

                return '<c r="'.$ref.'" s="'.($type === 'date' ? self::S_DATE : self::S_DATETIME).'"><v>'.$serial.'</v></c>';
        }

        return $this->text($ref, is_bool($value) ? ($value ? (string) __('Yes') : (string) __('No')) : (string) $value, self::S_DEFAULT);
    }

    protected function text(string $ref, string $text, int $style): string
    {
        if ($text === '') {
            return '<c r="'.$ref.'" s="'.$style.'"/>';
        }

        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->esc($text).'</t></is></c>';
    }

    /** Excel serial date: days since 1899-12-30 (fraction = time of day). */
    protected function serial(\Carbon\CarbonInterface $date, bool $withTime): string
    {
        // calendar fields as written (no time-zone shift): Excel dates have no zone
        $base = \Carbon\CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC');
        $days = (float) $base->diffInDays(\Carbon\CarbonImmutable::create($date->year, $date->month, $date->day, 0, 0, 0, 'UTC'), true);
        if ($withTime) {
            $days += ($date->hour * 3600 + $date->minute * 60 + $date->second) / 86400;
        }

        return rtrim(rtrim(number_format($days, 10, '.', ''), '0'), '.');
    }

    /** 0 → A, 25 → Z, 26 → AA */
    protected function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26).$s;
        }

        return $s;
    }

    protected function sheetName(string $title): string
    {
        $name = trim(preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $title));

        return mb_substr($name !== '' ? $name : 'Report', 0, 31);
    }

    /** XML-safe text: control characters dropped, markup escaped. */
    protected function esc(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="4"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/><numFmt numFmtId="166" formatCode="0.0&quot;%&quot;"/><numFmt numFmtId="167" formatCode="dd/mm/yyyy\ hh:mm"/></numFmts>'
            .'<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FF94A3B8"/></bottom><diagonal/></border><border><left/><right/><top style="thin"><color rgb="FF475569"/></top><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="14">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                                                                              // 0 default
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                                                // 1 title
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'                  // 2 header
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                                                                      // 3 money
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                                                                        // 4 int
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment horizontal="left"/></xf>'                                                    // 5 date
            .'<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                                                                                      // 6 percent
            .'<xf numFmtId="167" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"><alignment horizontal="left"/></xf>'                                                    // 7 datetime
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                                                // 8 muted
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="2" xfId="0" applyFont="1" applyBorder="1"/>'                                                                                // 9 total text
            .'<xf numFmtId="164" fontId="2" fillId="0" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>'                                                        // 10 total money
            .'<xf numFmtId="3" fontId="2" fillId="0" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1"/>'                                                          // 11 total int
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf>' // 12 header right
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                                                                                // 13 bold text
            .'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
