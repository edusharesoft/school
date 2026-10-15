<?php

namespace App\Services\Reports\Exporters;

use App\Services\Reports\ReportResult;

/** CSV with a UTF-8 BOM (Excel shows Bangla correctly): header row, data rows, and a totals row when the report has one. */
class CsvExporter
{
    /** @param resource $out */
    public function write(ReportResult $result, $out): void
    {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(fn ($c) => $this->safe((string) $c['label']), $result->columns));

        foreach ($result->rows as $row) {
            $line = [];
            foreach ($result->columns as $column) {
                $line[] = $this->safe(ReportResult::raw($row[$column['key']] ?? null, $column['type']));
            }
            fputcsv($out, $line);
        }

        if ($result->hasTotals()) {
            $totals = $result->totals();
            $line = [];
            foreach ($result->columns as $i => $column) {
                $line[] = $i === 0 && ! isset($totals[$column['key']]) ? (string) __('Total') : ($totals[$column['key']] ?? null);
            }
            fputcsv($out, $line);
        }
    }

    public function toFile(ReportResult $result, string $path): void
    {
        $out = fopen($path, 'wb');
        $this->write($result, $out);
        fclose($out);
    }

    /** Cells that a spreadsheet would read as a formula get a leading apostrophe (CSV injection guard). */
    protected function safe(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
