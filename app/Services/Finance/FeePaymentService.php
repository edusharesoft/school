<?php

namespace App\Services\Finance;

use App\Events\Finance\FeePaymentReceived;
use App\Models\Finance\Account;
use App\Models\Finance\FeeAllocation;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Finance\FeeRefund;
use App\Models\Finance\JournalLine;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money receipts, reversals and refunds.
 *   receipt   Dr Cash/Bank  /  Cr Fees receivable (settled invoices) + Cr Student advances (the rest)
 *   reversal  mirror of the receipt and of every advance application made from it; invoices reopen
 *   refund    Dr Student advances  /  Cr Cash/Bank   (only out of unused advance credit)
 * Partial payments leave invoices "partial"; over-payment becomes advance credit that new invoices use first.
 */
class FeePaymentService
{
    public function __construct(
        protected LedgerService $ledger,
        protected ChartOfAccounts $chart,
        protected FeeInvoiceService $invoices,
        protected SequenceService $sequences,
        protected TenantContext $tenant,
    ) {}

    /** @param int[] $invoiceIds  settle these first (in this order); otherwise the oldest due invoices */
    public function collect(Student $student, float $amount, string $method, ?Account $account, \DateTimeInterface|string $date,
        ?string $reference = null, ?string $notes = null, array $invoiceIds = [], ?User $user = null): FeePayment
    {
        $amount = round($amount, 2);
        $date = CarbonImmutable::parse($date)->startOfDay();
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be greater than zero.')]);
        }
        if ($date->gt(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['date' => __('A receipt cannot be dated in the future.')]);
        }
        if (! in_array($method, FeePayment::METHODS, true)) {
            throw ValidationException::withMessages(['method' => __('Unknown payment method.')]);
        }
        $account ??= $this->chart->forMethod($method);
        if (! $account->kind || $account->is_group || ! $account->is_active) {
            throw ValidationException::withMessages(['account_id' => __('Money can only be received into an active cash, bank or mobile-banking account.')]);
        }

        return DB::transaction(function () use ($student, $amount, $method, $account, $date, $reference, $notes, $invoiceIds, $user) {
            $open = FeeInvoice::query()->open()->where('student_id', $student->id)->lockForUpdate()->get();
            $ordered = $invoiceIds
                ? collect($invoiceIds)->map(fn ($id) => $open->firstWhere('id', (int) $id))->filter()->values()
                : $open->sortBy(fn ($i) => $i->due_date->format('Ymd').sprintf('%010d', $i->id))->values();

            $payment = new FeePayment([
                'receipt_no' => $this->nextNumber($date), 'student_id' => $student->id, 'date' => $date->toDateString(), 'amount' => $amount,
                'unallocated' => $amount, 'method' => $method, 'account_id' => $account->id,
                'reference' => $reference ? mb_substr($reference, 0, 100) : null, 'notes' => $notes ? mb_substr($notes, 0, 255) : null,
            ]);
            $payment->forceFill(['status' => 'completed', 'received_by' => $user?->id])->save();

            $left = $amount;
            $allocated = 0.0;
            foreach ($ordered as $invoice) {
                if ($left <= 0) {
                    break;
                }
                $portion = round(min($left, $invoice->balance), 2);
                if ($portion <= 0) {
                    continue;
                }
                FeeAllocation::query()->create(['fee_payment_id' => $payment->id, 'fee_invoice_id' => $invoice->id, 'amount' => $portion, 'from_credit' => false]);
                $left = round($left - $portion, 2);
                $allocated = round($allocated + $portion, 2);
            }
            $payment->forceFill(['unallocated' => $left])->save();

            $this->ledger->post('receipt', $date, __('Receipt :no — :name', ['no' => $payment->receipt_no, 'name' => $student->name]), array_values(array_filter([
                ['account_id' => $account->id, 'debit' => $amount, 'description' => $method.($reference ? ' '.$reference : '')],
                $allocated > 0 ? ['account_id' => $this->chart->id('fees_receivable'), 'credit' => $allocated, 'student_id' => $student->id] : null,
                $left > 0 ? ['account_id' => $this->chart->id('student_advance'), 'credit' => $left, 'student_id' => $student->id, 'description' => __('Advance')] : null,
            ])), $payment, $user);

            foreach ($ordered as $invoice) {
                $this->invoices->recalculate($invoice);
            }
            FeePaymentReceived::dispatch($payment);

            return $payment->fresh(['allocations.invoice']);
        });
    }

    public function reverse(FeePayment $payment, string $reason, User $user): FeePayment
    {
        if ($payment->isReversed()) {
            throw ValidationException::withMessages(['reason' => __('This receipt is already reversed.')]);
        }
        $refunded = FeeRefund::query()->where('student_id', $payment->student_id)->get()
            ->contains(fn (FeeRefund $r) => collect($r->sources)->contains(fn ($s) => (int) ($s['payment_id'] ?? 0) === $payment->id));
        if ($refunded) {
            throw ValidationException::withMessages(['reason' => __('Part of this receipt was refunded as advance; it cannot be reversed.')]);
        }

        return DB::transaction(function () use ($payment, $reason, $user) {
            $invoiceIds = [];
            foreach ($payment->allocations()->get() as $allocation) {
                $entry = $allocation->entry;
                if ($entry && $entry->status === 'posted') {
                    $this->ledger->reverse($entry, $reason, $user);
                }
                $invoiceIds[] = $allocation->fee_invoice_id;
                $allocation->delete();
            }
            foreach ($this->ledger->entriesFor($payment)->filter(fn ($e) => $e->status === 'posted' && ! $e->reversal_of_id) as $entry) {
                $this->ledger->reverse($entry, __('Receipt :no reversed — :reason', ['no' => $payment->receipt_no, 'reason' => $reason]), $user);
            }
            $payment->forceFill(['status' => 'reversed', 'unallocated' => 0, 'reverse_reason' => mb_substr($reason, 0, 255), 'reversed_at' => now(), 'reversed_by' => $user->id])->save();

            foreach (FeeInvoice::query()->whereIn('id', array_unique($invoiceIds))->get() as $invoice) {
                $this->invoices->recalculate($invoice);
            }

            return $payment;
        });
    }

    public function refund(Student $student, float $amount, string $method, ?Account $account, string $reason, \DateTimeInterface|string $date, User $user, ?string $reference = null): FeeRefund
    {
        $amount = round($amount, 2);
        $credit = $this->credit($student);
        if ($amount <= 0 || $amount > $credit) {
            throw ValidationException::withMessages(['amount' => __('A refund must be between 0 and the advance credit (:credit).', ['credit' => number_format($credit, 2)])]);
        }
        $account ??= $this->chart->forMethod($method);
        if (! $account->kind || ! $account->is_active) {
            throw ValidationException::withMessages(['account_id' => __('Refunds are paid from an active cash, bank or mobile-banking account.')]);
        }
        $date = CarbonImmutable::parse($date);

        return DB::transaction(function () use ($student, $amount, $method, $account, $reason, $date, $user, $reference) {
            $sources = [];
            $left = $amount;
            foreach (FeePayment::query()->where('student_id', $student->id)->where('status', 'completed')->where('unallocated', '>', 0)->orderBy('date')->orderBy('id')->lockForUpdate()->get() as $p) {
                if ($left <= 0) {
                    break;
                }
                $take = round(min($left, $p->unallocated), 2);
                $p->forceFill(['unallocated' => round($p->unallocated - $take, 2)])->save();
                $sources[] = ['payment_id' => $p->id, 'amount' => $take];
                $left = round($left - $take, 2);
            }

            $refund = new FeeRefund([
                'refund_no' => sprintf('%s-RFD%s-%04d', strtoupper($this->tenant->branch()->code), $date->format('y'), $this->sequences->next('fee_refund', $date->format('Y'))),
                'student_id' => $student->id, 'date' => $date->toDateString(), 'amount' => $amount, 'method' => $method, 'account_id' => $account->id,
                'reference' => $reference, 'reason' => mb_substr($reason, 0, 255), 'sources' => $sources,
            ]);
            $refund->forceFill(['refunded_by' => $user->id])->save();

            $this->ledger->post('refund', $date, __('Refund :no — :name', ['no' => $refund->refund_no, 'name' => $student->name]), [
                ['account_id' => $this->chart->id('student_advance'), 'debit' => $amount, 'student_id' => $student->id],
                ['account_id' => $account->id, 'credit' => $amount, 'description' => $reason],
            ], $refund, $user);

            return $refund;
        });
    }

    /** Unused advance credit of a student. */
    public function credit(Student $student): float
    {
        return round((float) FeePayment::query()->where('student_id', $student->id)->where('status', 'completed')->sum('unallocated'), 2);
    }

    public function outstanding(Student $student): float
    {
        return round((float) FeeInvoice::query()->open()->where('student_id', $student->id)->sum('balance'), 2);
    }

    /**
     * Student ledger from the books (fees receivable + student advances): charges are debits, receipts / waivers are
     * credits; the running balance is what the student owes (negative = advance).
     *
     * @return Collection<int,array{date:string, entry_no:string, narration:string, debit:float, credit:float, balance:float, type:string}>
     */
    public function ledger(Student $student): Collection
    {
        $accounts = [$this->chart->id('fees_receivable'), $this->chart->id('student_advance')];
        $rows = JournalLine::query()->with('entry:id,entry_no,narration,type,date')
            ->where('student_id', $student->id)->whereIn('account_id', $accounts)->orderBy('date')->orderBy('journal_entry_id')->get()
            ->groupBy('journal_entry_id');

        $balance = 0.0;
        $out = collect();
        foreach ($rows as $lines) {
            $net = round($lines->sum('debit') - $lines->sum('credit'), 2);
            if ($net == 0.0) {
                continue; // internal move between receivable and advance (credit applied)
            }
            $entry = $lines->first()->entry;
            $balance = round($balance + $net, 2);
            $out->push([
                'date' => $lines->first()->date->toDateString(), 'entry_no' => $entry->entry_no, 'narration' => $entry->narration, 'type' => $entry->type,
                'debit' => $net > 0 ? $net : 0.0, 'credit' => $net < 0 ? -$net : 0.0, 'balance' => $balance,
            ]);
        }

        return $out;
    }

    protected function nextNumber(CarbonImmutable $date): string
    {
        return sprintf('%s-RCT%s-%05d', strtoupper($this->tenant->branch()->code), $date->format('y'), $this->sequences->next('fee_receipt', $date->format('Y')));
    }
}
