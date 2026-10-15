<?php

namespace App\Services\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\FinancialYear;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalLine;
use App\Models\User;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Double-entry posting. Every entry balances (Σ debit = Σ credit), uses active non-group accounts of the
 * branch and falls in an open financial year. Posted entries are never edited: reverse() posts the mirror
 * entry and marks the original "reversed".
 */
class LedgerService
{
    public const PREFIX = ['invoice' => 'SI', 'receipt' => 'RV', 'payment' => 'PV', 'refund' => 'PV', 'expense' => 'PV', 'journal' => 'JV',
        'contra' => 'CV', 'reversal' => 'JV', 'payroll' => 'JV'];

    public function __construct(protected TenantContext $tenant, protected SequenceService $sequences, protected FinanceSettings $settings) {}

    /**
     * @param  array<int,array{account_id:int, debit?:float|int|string, credit?:float|int|string, student_id?:?int, description?:?string}>  $lines
     */
    public function post(string $type, \DateTimeInterface|string $date, string $narration, array $lines, ?Model $source = null, ?User $user = null): JournalEntry
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $clean = [];
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $d = round((float) ($line['debit'] ?? 0), 2);
            $c = round((float) ($line['credit'] ?? 0), 2);
            if ($d == 0.0 && $c == 0.0) {
                continue;
            }
            if ($d < 0 || $c < 0 || ($d > 0 && $c > 0)) {
                throw ValidationException::withMessages(['lines' => __('Each line needs either a debit or a credit amount, never both or a negative amount.')]);
            }
            $clean[] = ['account_id' => (int) $line['account_id'], 'debit' => $d, 'credit' => $c,
                'student_id' => $line['student_id'] ?? null, 'description' => isset($line['description']) ? mb_substr((string) $line['description'], 0, 255) : null];
            $debit += $d;
            $credit += $c;
        }

        if (count($clean) < 2 || round($debit, 2) !== round($credit, 2) || $debit <= 0) {
            throw ValidationException::withMessages(['lines' => __('The entry does not balance: debit :debit, credit :credit.', ['debit' => number_format($debit, 2), 'credit' => number_format($credit, 2)])]);
        }

        $ids = array_unique(array_column($clean, 'account_id'));
        $valid = Account::query()->postable()->whereIn('id', $ids)->pluck('id')->all();
        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages(['lines' => __('Entries can only be posted to active, non-group accounts of this branch.')]);
        }

        return DB::transaction(function () use ($type, $date, $narration, $clean, $debit, $source, $user) {
            $year = $this->yearFor($date);
            $entry = JournalEntry::query()->create([
                'financial_year_id' => $year->id,
                'entry_no' => $this->nextNumber($type, $year),
                'date' => $date->toDateString(),
                'type' => $type,
                'narration' => mb_substr($narration, 0, 255),
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'amount' => round($debit, 2),
                'created_by' => $user?->id,
            ]);
            foreach ($clean as $line) {
                JournalLine::query()->create($line + ['journal_entry_id' => $entry->id, 'date' => $date->toDateString()]);
            }

            return $entry;
        });
    }

    /** Post the mirror entry (debits ↔ credits) and mark the original reversed. */
    public function reverse(JournalEntry $entry, string $reason, ?User $user = null, \DateTimeInterface|string|null $date = null): JournalEntry
    {
        if ($entry->isReversed() || $entry->reversal_of_id) {
            throw ValidationException::withMessages(['entry' => __('Entry :no is already reversed or is itself a reversal.', ['no' => $entry->entry_no])]);
        }

        return DB::transaction(function () use ($entry, $reason, $user, $date) {
            $lines = $entry->lines()->get()->map(fn (JournalLine $l) => [
                'account_id' => $l->account_id, 'debit' => $l->credit, 'credit' => $l->debit, 'student_id' => $l->student_id, 'description' => $l->description,
            ])->all();
            $reversal = $this->postUnchecked('reversal', $date ?? now(), __('Reversal of :no', ['no' => $entry->entry_no]).' — '.$reason, $lines, $entry->source, $user);
            $reversal->forceFill(['reversal_of_id' => $entry->id])->save();
            $entry->forceFill(['status' => 'reversed', 'reversed_by_id' => $reversal->id])->save();

            return $reversal;
        });
    }

    /** Entries of a source document (invoice, receipt …), newest first. */
    public function entriesFor(Model $source): Collection
    {
        return JournalEntry::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->latest('id')->get();
    }

    /** Balance of an account up to a date (debit-normal accounts positive when debit > credit). */
    public function balance(Account $account, \DateTimeInterface|string|null $until = null): float
    {
        $row = JournalLine::query()->where('account_id', $account->id)
            ->when($until, fn ($q) => $q->whereDate('date', '<=', CarbonImmutable::parse($until)->toDateString()))
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->toBase()->first();
        $net = (float) $row->d - (float) $row->c;

        return round($account->isDebitNormal() ? $net : -$net, 2);
    }

    public function yearFor(\DateTimeInterface|string $date): FinancialYear
    {
        $date = CarbonImmutable::parse($date);
        $year = $this->yearOf($date);
        if ($year->is_closed) {
            throw ValidationException::withMessages(['date' => __('Financial year :year is closed; entries dated :date are not allowed.', ['year' => $year->name, 'date' => $date->toDateString()])]);
        }

        return $year;
    }

    /** The financial year containing $date (created from the fy_start_month setting if missing), open or closed. */
    public function yearOf(\DateTimeInterface|string $date): FinancialYear
    {
        $date = CarbonImmutable::parse($date);
        $year = FinancialYear::query()->whereDate('starts_on', '<=', $date->toDateString())->whereDate('ends_on', '>=', $date->toDateString())->first();
        if (! $year) {
            $startMonth = max(1, min(12, (int) $this->settings->get('fy_start_month')));
            $startYear = $date->month >= $startMonth ? $date->year : $date->year - 1;
            $start = CarbonImmutable::create($startYear, $startMonth, 1);
            $end = $start->addYear()->subDay();
            $year = FinancialYear::query()->create([
                'name' => $startMonth === 1 ? (string) $startYear : $startYear.'-'.substr((string) ($startYear + 1), -2),
                'starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(),
            ]);
        }

        return $year;
    }

    /** Same as post() but for reversals of already-valid entries (accounts may have been deactivated since). */
    protected function postUnchecked(string $type, \DateTimeInterface|string $date, string $narration, array $lines, ?Model $source, ?User $user): JournalEntry
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $year = $this->yearFor($date);
        $entry = JournalEntry::query()->create([
            'financial_year_id' => $year->id, 'entry_no' => $this->nextNumber($type, $year), 'date' => $date->toDateString(), 'type' => $type,
            'narration' => mb_substr($narration, 0, 255), 'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(),
            'amount' => round(array_sum(array_column($lines, 'debit')), 2), 'created_by' => $user?->id,
        ]);
        foreach ($lines as $line) {
            JournalLine::query()->create($line + ['journal_entry_id' => $entry->id, 'date' => $date->toDateString()]);
        }

        return $entry;
    }

    protected function nextNumber(string $type, FinancialYear $year): string
    {
        $prefix = self::PREFIX[$type] ?? 'JV';
        $fy = preg_replace('/\D/', '', $year->name);

        return sprintf('%s-%s%s-%05d', strtoupper($this->tenant->branch()->code), $prefix, substr($fy, -4), $this->sequences->next('journal_'.$prefix, (string) $year->id));
    }
}
