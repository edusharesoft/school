<?php

namespace App\Services\Reports;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * One report of the reports centre. A report declares who may see it, which filters it takes and builds a
 * ReportResult; the centre draws the screen and the exports (CSV, Excel, PDF, background) from that result.
 *
 * Branch reports run inside the user's branch (BranchScope isolates the data). Central reports (scope() = 'central')
 * run for the whole institution and receive their branch ids from the DashboardService.
 *
 * Filter specs: ['name' => 'from', 'type' => 'date|month|select|text|bool|number', 'label' => 'From',
 *                'options' => [value => label] | Closure, 'default' => mixed | Closure, 'empty' => 'All']
 */
abstract class Report
{
    public const MAX_RANGE_DAYS = 366;

    /** Unique key: "<group>.<name>", e.g. "library.overdue". */
    abstract public function key(): string;

    abstract public function title(): string;

    /** Permission that opens the report. */
    abstract public function permission(): string;

    abstract public function run(array $filters): ReportResult;

    public function description(): string
    {
        return '';
    }

    /** Permission that allows exporting; finance reports keep their own. */
    public function exportPermission(): string
    {
        return 'reports.export';
    }

    /** branch | central */
    public function scope(): string
    {
        return 'branch';
    }

    /** @return array<int,array<string,mixed>> */
    public function filters(): array
    {
        return [];
    }

    public function group(): string
    {
        return explode('.', $this->key(), 2)[0];
    }

    public function canView(?User $user): bool
    {
        return $user !== null && $user->can($this->permission());
    }

    public function canExport(?User $user): bool
    {
        return $this->canView($user) && $user->can($this->exportPermission());
    }

    /**
     * Turn raw request input into clean filter values: every declared filter is present, typed and valid,
     * anything unknown or invalid falls back to its default.
     *
     * @return array<string,mixed>
     */
    public function normalize(array $input): array
    {
        $clean = [];
        foreach ($this->filters() as $spec) {
            $name = $spec['name'];
            $default = $spec['default'] ?? null;
            $default = $default instanceof \Closure ? $default() : $default;
            $raw = $input[$name] ?? null;

            $clean[$name] = match ($spec['type']) {
                'date' => $this->cleanDate($raw) ?? ($default !== null ? $this->cleanDate($default) : null),
                'month' => $this->cleanMonth($raw) ?? ($default !== null ? $this->cleanMonth($default) : null),
                'select' => $this->cleanSelect($spec, $raw) ?? ($default !== null ? $this->cleanSelect($spec, $default) : null),
                'bool' => filter_var($raw ?? $default ?? false, FILTER_VALIDATE_BOOLEAN),
                'number' => is_numeric($raw) ? (int) $raw : (is_numeric($default) ? (int) $default : null),
                default => ($t = trim((string) ($raw ?? $default ?? ''))) === '' ? null : mb_substr($t, 0, 100),
            };
        }

        return $clean;
    }

    /** @return array<string,string> label => shown value, only for filters that are set */
    public function filterSummary(array $filters): array
    {
        $out = [];
        foreach ($this->filters() as $spec) {
            $value = $filters[$spec['name']] ?? null;
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            $out[(string) __($spec['label'])] = match ($spec['type']) {
                'date' => CarbonImmutable::parse($value)->format('d/m/Y'),
                'month' => CarbonImmutable::parse($value.'-01')->format('F Y'),
                'select' => (string) ($this->optionsOf($spec)[$value] ?? $value),
                'bool' => (string) __('Yes'),
                default => (string) $value,
            };
        }

        return $out;
    }

    /** Options of a select filter (evaluates closures). @return array<string|int,string> */
    public function optionsOf(array $spec): array
    {
        $options = $spec['options'] ?? [];

        return (array) ($options instanceof \Closure ? $options() : $options);
    }

    /**
     * The date range of the usual from / to filters: ordered, at most a year long.
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    protected function range(array $filters, string $from = 'from', string $to = 'to'): array
    {
        $a = ! empty($filters[$from]) ? CarbonImmutable::parse($filters[$from]) : CarbonImmutable::today()->startOfMonth();
        $b = ! empty($filters[$to]) ? CarbonImmutable::parse($filters[$to]) : CarbonImmutable::today();
        if ($b->lt($a)) {
            [$a, $b] = [$b, $a];
        }
        if ($a->diffInDays($b) > static::MAX_RANGE_DAYS) {
            $a = $b->subDays(static::MAX_RANGE_DAYS);
        }

        return [$a->startOfDay(), $b->startOfDay()];
    }

    protected function fromDefault(): \Closure
    {
        return fn () => CarbonImmutable::today()->startOfMonth()->toDateString();
    }

    protected function toDefault(): \Closure
    {
        return fn () => CarbonImmutable::today()->toDateString();
    }

    /** Standard "From" / "To" date filters. */
    protected function periodFilters(): array
    {
        return [
            ['name' => 'from', 'type' => 'date', 'label' => 'From', 'default' => $this->fromDefault()],
            ['name' => 'to', 'type' => 'date', 'label' => 'To', 'default' => $this->toDefault()],
        ];
    }

    protected function result(array $columns, array $rows): ReportResult
    {
        return new ReportResult($this->title(), $columns, $rows);
    }

    private function cleanDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value)->toDateString() === $value ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanMonth(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return null;
        }

        return $value;
    }

    private function cleanSelect(array $spec, mixed $value): string|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        $options = $this->optionsOf($spec);

        foreach ($options as $key => $label) {
            if ((string) $key === (string) $value) {
                return $key;
            }
        }

        return null;
    }
}
