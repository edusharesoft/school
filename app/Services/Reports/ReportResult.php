<?php

namespace App\Services\Reports;

use Carbon\CarbonInterface;

/**
 * The tabular result of one report run. Screens, CSV, Excel and PDF are all drawn from this one object, so a
 * report is written once. Rows are arrays keyed by column key; values are raw (numbers, Y-m-d strings, text).
 *
 * Column types: text | int | number (2 decimals) | money (2 decimals) | percent (12.5 = 12.5 %) | date | datetime
 */
class ReportResult
{
    public const TYPES = ['text', 'int', 'number', 'money', 'percent', 'date', 'datetime'];

    /** @param array<int,array{key:string,label:string,type:string,total:bool}> $columns
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $filters  label => shown value (what was asked for)
     * @param array<string,mixed> $summary   label => value shown above the table (e.g. "Books in stock" => 120)
     * @param string[] $notes */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
        public ?string $subtitle = null,
        public array $filters = [],
        public array $summary = [],
        public array $notes = [],
    ) {}

    /** A column definition. */
    public static function col(string $key, string $label, string $type = 'text', bool $total = false): array
    {
        return ['key' => $key, 'label' => $label, 'type' => in_array($type, self::TYPES, true) ? $type : 'text', 'total' => $total];
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    public function hasTotals(): bool
    {
        foreach ($this->columns as $column) {
            if ($column['total']) {
                return $this->rows !== [];
            }
        }

        return false;
    }

    /** @return array<string,float|int|null> key => sum for the total columns */
    public function totals(): array
    {
        $totals = [];
        foreach ($this->columns as $column) {
            if (! $column['total']) {
                continue;
            }
            $sum = 0.0;
            foreach ($this->rows as $row) {
                $sum += (float) ($row[$column['key']] ?? 0);
            }
            $totals[$column['key']] = $column['type'] === 'int' ? (int) round($sum) : round($sum, 2);
        }

        return $totals;
    }

    /** The first <= n rows (screen preview). */
    public function head(int $n): static
    {
        $copy = clone $this;
        $copy->rows = array_slice($this->rows, 0, $n);

        return $copy;
    }

    public function isNumeric(array $column): bool
    {
        return in_array($column['type'], ['int', 'number', 'money', 'percent'], true);
    }

    /** Human text of a value (screen and PDF): 1,234.50 · 05/03/2026 · 12.5% */
    public static function display(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'int' => number_format((float) $value, 0),
            'number', 'money' => number_format((float) $value, 2),
            'percent' => rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%',
            'date' => self::carbon($value)?->format('d/m/Y') ?? (string) $value,
            'datetime' => self::carbon($value)?->format('d/m/Y H:i') ?? (string) $value,
            default => is_bool($value) ? ($value ? __('Yes') : __('No')) : (string) $value,
        };
    }

    /** Value as it is written to CSV: plain numbers, ISO dates. */
    public static function raw(mixed $value, string $type): string|int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'int' => (int) round((float) $value),
            'number', 'money' => round((float) $value, 2),
            'percent' => round((float) $value, 1),
            'date' => self::carbon($value)?->toDateString() ?? (string) $value,
            'datetime' => self::carbon($value)?->format('Y-m-d H:i') ?? (string) $value,
            default => is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value,
        };
    }

    public static function carbon(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \Carbon\CarbonImmutable::instance($value);
        }
        try {
            return $value ? \Carbon\CarbonImmutable::parse((string) $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
