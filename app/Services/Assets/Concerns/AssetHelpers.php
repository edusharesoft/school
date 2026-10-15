<?php

namespace App\Services\Assets\Concerns;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/** Shared by the asset services: document numbers, reversing a document's journal entries, small string helpers. */
trait AssetHelpers
{
    /** Document number: BRANCHCODE-PREFIXyy-00001, a per-branch yearly sequence. */
    public function docNo(string $prefix, \DateTimeInterface|string $date): string
    {
        $date = CarbonImmutable::parse($date);

        return sprintf('%s-%s%s-%05d', strtoupper($this->tenant->branch()->code), $prefix, $date->format('y'), $this->sequences->next('asset_'.strtolower($prefix), $date->format('Y')));
    }

    /** Posted, un-reversed journal entries of a document are reversed with their mirror entries. */
    protected function reverseEntries(Model $source, string $reason, User $user): void
    {
        foreach ($this->ledger->entriesFor($source)->filter(fn ($e) => $e->status === 'posted' && ! $e->reversal_of_id) as $entry) {
            $this->ledger->reverse($entry, $reason, $user);
        }
    }

    protected function clip(?string $text, int $max = 255): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    /** YYYY-MM of a date. */
    public static function periodOf(\DateTimeInterface|string $date): string
    {
        return CarbonImmutable::parse($date)->format('Y-m');
    }

    /** The month after a YYYY-MM period. */
    public static function nextPeriod(string $period): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $period)->addMonthNoOverflow()->format('Y-m');
    }

    public static function previousPeriod(string $period): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $period)->subMonthNoOverflow()->format('Y-m');
    }

    public static function validPeriod(string $period): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period);
    }
}
