<?php

namespace App\Services\Finance;

use App\Events\Finance\FeeInvoiceIssued;
use App\Models\Academic\AcademicSession;
use App\Models\Finance\FeeAllocation;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Finance\FeeStructure;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\StudentFeeAdjustment;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use App\Models\User;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fee invoices: monthly generation from fee structures (idempotent per student & month), one-off invoices,
 * discounts, advance-credit application, late fees, waivers and cancellation — every step posted to the ledger:
 *   issue      Dr Fees receivable (net) + Dr Fee discounts (discount)  /  Cr Fee income per head (gross)
 *   late fee   Dr Fees receivable  /  Cr Late fees & fines
 *   waiver     Dr Fee discounts & waivers  /  Cr Fees receivable
 *   credit     Dr Student advances  /  Cr Fees receivable       (advance applied to the invoice)
 *   cancel     reversal of every entry above + Dr Fees receivable / Cr Student advances for the amount already paid
 */
class FeeInvoiceService
{
    public function __construct(
        protected LedgerService $ledger,
        protected ChartOfAccounts $chart,
        protected FinanceSettings $settings,
        protected SequenceService $sequences,
        protected TenantContext $tenant,
    ) {}

    /** @return array{created:int, skipped:int, empty:int, period:string} */
    public function generateForPeriod(string $period, ?int $classId = null, ?User $user = null): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw ValidationException::withMessages(['period' => __('The month must look like 2026-07.')]);
        }
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period.'-01')->startOfDay();
        $month = (int) $start->month;

        $session = AcademicSession::query()->whereDate('starts_on', '<=', $start->toDateString())->whereDate('ends_on', '>=', $start->toDateString())->first()
            ?? AcademicSession::query()->current()->first();
        if (! $session) {
            throw ValidationException::withMessages(['period' => __('No academic session covers :period.', ['period' => $period])]);
        }

        $structures = FeeStructure::query()->with('head')->where('academic_session_id', $session->id)
            ->whereHas('head', fn ($q) => $q->where('is_active', true))->get()->groupBy('school_class_id');
        $adjustments = StudentFeeAdjustment::query()->with('head')->where('academic_session_id', $session->id)->where('is_active', true)->get()->groupBy('student_id');

        $enrollments = StudentEnrollment::query()->with('student:id,name,status')->where('academic_session_id', $session->id)->where('status', 'enrolled')
            ->when($classId, fn ($q, $id) => $q->where('school_class_id', $id))->orderBy('school_class_id')->orderBy('roll_no')->get();

        $existing = FeeInvoice::query()->whereIn('generated_key', $enrollments->map(fn ($e) => "auto:{$e->student_id}:{$period}"))->pluck('generated_key')->flip();
        $siblings = $this->siblingsEligible($enrollments);
        $siblingPct = (float) $this->settings->get('sibling_discount_percent');
        $due = $start->setDay(min(max(1, (int) $this->settings->get('due_day')), $start->daysInMonth));

        $stats = ['created' => 0, 'skipped' => 0, 'empty' => 0, 'period' => $period];
        foreach ($enrollments as $enrollment) {
            $key = "auto:{$enrollment->student_id}:{$period}";
            if ($existing->has($key)) {
                $stats['skipped']++;

                continue;
            }
            $items = $this->buildItems($enrollment, $month, $structures->get($enrollment->school_class_id, collect()),
                $adjustments->get($enrollment->student_id, collect()), $siblings->has($enrollment->student_id) ? $siblingPct : 0.0);
            if ($items === []) {
                $stats['empty']++;

                continue;
            }

            $this->create($enrollment->student, $enrollment, $items, __('Fees for :month', ['month' => $start->format('F Y')]),
                $start, $due, $period, $key, $user);
            $stats['created']++;
        }

        return $stats;
    }

    /**
     * Items of one student for one month: structure amounts (group-specific rows win over "all groups"), extra fees,
     * then discounts (per head or all heads; percent or fixed) and the automatic sibling discount on tuition.
     *
     * @return array<int,array{fee_head_id:?int, description:string, amount:float, discount:float, discount_note:?string}>
     */
    public function buildItems(StudentEnrollment $enrollment, int $month, Collection $structures, Collection $adjustments, float $siblingPct = 0.0): array
    {
        $byHead = [];
        foreach ($structures as $s) {
            if (! $s->billsIn($month) || ($s->study_group_id && (int) $s->study_group_id !== (int) $enrollment->study_group_id)) {
                continue;
            }
            $current = $byHead[$s->fee_head_id] ?? null;
            if (! $current || (! $current->study_group_id && $s->study_group_id)) {
                $byHead[$s->fee_head_id] = $s;
            }
        }

        $items = [];
        foreach ($byHead as $s) {
            if ($s->amount > 0) {
                $items[] = ['fee_head_id' => $s->fee_head_id, 'category' => $s->head->category, 'description' => $s->head->name, 'amount' => round($s->amount, 2), 'discount' => 0.0, 'discount_note' => null];
            }
        }
        foreach ($adjustments->where('type', 'extra_fee') as $a) {
            if ($a->appliesIn($month) && $a->value > 0 && $a->head) {
                $items[] = ['fee_head_id' => $a->fee_head_id, 'category' => $a->head->category, 'description' => $a->head->name.($a->reason ? ' ('.$a->reason.')' : ''), 'amount' => round($a->value, 2), 'discount' => 0.0, 'discount_note' => null];
            }
        }
        if ($items === []) {
            return [];
        }

        $discounts = $adjustments->filter(fn ($a) => $a->isDiscount() && $a->appliesIn($month));
        foreach ($discounts as $a) {
            $label = __(ucfirst(str_replace('_', ' ', $a->type)));
            if ($a->mode === 'percent') {
                foreach ($items as &$item) {
                    if (! $a->fee_head_id || $a->fee_head_id === $item['fee_head_id']) {
                        $this->addDiscount($item, round($item['amount'] * min(100, $a->value) / 100, 2), $label);
                    }
                }
                unset($item);
            } else {
                $left = round($a->value, 2);
                foreach ($items as &$item) {
                    if ($left > 0 && (! $a->fee_head_id || $a->fee_head_id === $item['fee_head_id'])) {
                        $left -= $this->addDiscount($item, $left, $label);
                    }
                }
                unset($item);
            }
        }

        if ($siblingPct > 0 && ! $adjustments->contains('type', 'sibling')) {
            foreach ($items as &$item) {
                if ($item['category'] === 'tuition') {
                    $this->addDiscount($item, round($item['amount'] * min(100, $siblingPct) / 100, 2), __('Sibling'));
                }
            }
            unset($item);
        }

        return array_map(fn ($i) => array_diff_key($i, ['category' => true]), $items);
    }

    /**
     * @param  array<int,array{fee_head_id:?int, description:string, amount:float|int|string, discount?:float|int|string, discount_note?:?string, is_late_fee?:bool}>  $items
     */
    public function create(Student $student, ?StudentEnrollment $enrollment, array $items, string $title, \DateTimeInterface|string $issueDate,
        \DateTimeInterface|string $dueDate, ?string $period = null, ?string $key = null, ?User $user = null, ?string $notes = null): FeeInvoice
    {
        $issue = CarbonImmutable::parse($issueDate);
        $due = CarbonImmutable::parse($dueDate);
        if ($due->lt($issue)) {
            throw ValidationException::withMessages(['due_date' => __('The due date cannot be before the issue date.')]);
        }
        $clean = [];
        foreach ($items as $i) {
            $amount = round((float) $i['amount'], 2);
            $discount = round(min($amount, max(0, (float) ($i['discount'] ?? 0))), 2);
            if ($amount <= 0) {
                continue;
            }
            $clean[] = ['fee_head_id' => $i['fee_head_id'] ?? null, 'description' => mb_substr((string) $i['description'], 0, 150), 'amount' => $amount,
                'discount' => $discount, 'discount_note' => $i['discount_note'] ?? null, 'is_late_fee' => (bool) ($i['is_late_fee'] ?? false)];
        }
        if ($clean === []) {
            throw ValidationException::withMessages(['items' => __('Add at least one fee with an amount.')]);
        }
        $enrollment ??= $student->currentEnrollment;

        return DB::transaction(function () use ($student, $enrollment, $clean, $title, $issue, $due, $period, $key, $user, $notes) {
            $subtotal = round(array_sum(array_column($clean, 'amount')), 2);
            $discount = round(array_sum(array_column($clean, 'discount')), 2);
            $invoice = new FeeInvoice([
                'invoice_no' => $this->nextNumber($issue),
                'student_id' => $student->id,
                'student_enrollment_id' => $enrollment?->id,
                'academic_session_id' => $enrollment?->academic_session_id,
                'school_class_id' => $enrollment?->school_class_id,
                'section_id' => $enrollment?->section_id,
                'period' => $period,
                'title' => mb_substr($title, 0, 150),
                'issue_date' => $issue->toDateString(),
                'due_date' => $due->toDateString(),
                'subtotal' => $subtotal,
                'discount' => $discount,
                'late_fee' => 0,
                'total' => round($subtotal - $discount, 2),
                'paid' => 0,
                'waived' => 0,
                'balance' => round($subtotal - $discount, 2),
                'status' => 'unpaid',
                'generated_key' => $key,
                'notes' => $notes ? mb_substr($notes, 0, 255) : null,
            ]);
            $invoice->forceFill(['created_by' => $user?->id])->save();
            $invoice->items()->createMany($clean);

            $this->postIssue($invoice, $clean, $user);
            $this->applyCredit($invoice, $user);
            $invoice = $this->recalculate($invoice);
            FeeInvoiceIssued::dispatch($invoice);

            return $invoice;
        });
    }

    /** Use the student's advance credit (unallocated receipts, oldest first) against an open invoice. */
    public function applyCredit(FeeInvoice $invoice, ?User $user = null): float
    {
        $invoice = $this->recalculate($invoice);
        if (! $invoice->isOpen() || $invoice->balance <= 0) {
            return 0.0;
        }

        $applied = 0.0;
        $payments = FeePayment::query()->where('student_id', $invoice->student_id)->where('status', 'completed')->where('unallocated', '>', 0)
            ->orderBy('date')->orderBy('id')->lockForUpdate()->get();
        foreach ($payments as $payment) {
            $need = round($invoice->balance - $applied, 2);
            if ($need <= 0) {
                break;
            }
            $amount = round(min($need, $payment->unallocated), 2);
            $allocation = FeeAllocation::query()->create(['fee_payment_id' => $payment->id, 'fee_invoice_id' => $invoice->id, 'amount' => $amount, 'from_credit' => true]);
            $entry = $this->ledger->post('journal', now(), __('Advance applied to :invoice', ['invoice' => $invoice->invoice_no]), [
                ['account_id' => $this->chart->id('student_advance'), 'debit' => $amount, 'student_id' => $invoice->student_id],
                ['account_id' => $this->chart->id('fees_receivable'), 'credit' => $amount, 'student_id' => $invoice->student_id],
            ], $allocation, $user);
            $allocation->update(['journal_entry_id' => $entry->id]);
            $payment->forceFill(['unallocated' => round($payment->unallocated - $amount, 2)])->save();
            $applied += $amount;
        }

        if ($applied > 0) {
            $this->recalculate($invoice);
        }

        return round($applied, 2);
    }

    public function recalculate(FeeInvoice $invoice): FeeInvoice
    {
        $invoice->refresh();
        if ($invoice->status === 'cancelled') {
            return $invoice;
        }
        $paid = round((float) $invoice->allocations()->sum('amount'), 2);
        $balance = round($invoice->total - $paid - $invoice->waived, 2);
        $status = $balance <= 0.004 ? 'paid' : (($paid + $invoice->waived) > 0 ? 'partial' : 'unpaid');
        $invoice->forceFill(['paid' => $paid, 'balance' => max(0, $balance), 'status' => $status])->save();

        return $invoice;
    }

    public function waive(FeeInvoice $invoice, float $amount, string $reason, User $user): FeeInvoice
    {
        $invoice = $this->recalculate($invoice);
        $amount = round($amount, 2);
        if (! $invoice->isOpen() || $amount <= 0 || $amount > $invoice->balance) {
            throw ValidationException::withMessages(['amount' => __('The waiver must be between 0 and the open balance (:balance).', ['balance' => number_format($invoice->balance, 2)])]);
        }

        return DB::transaction(function () use ($invoice, $amount, $reason, $user) {
            $this->ledger->post('journal', now(), __('Waiver on :invoice — :reason', ['invoice' => $invoice->invoice_no, 'reason' => $reason]), [
                ['account_id' => $this->chart->id('fee_discount'), 'debit' => $amount, 'student_id' => $invoice->student_id, 'description' => __('Waiver')],
                ['account_id' => $this->chart->id('fees_receivable'), 'credit' => $amount, 'student_id' => $invoice->student_id],
            ], $invoice, $user);
            $invoice->forceFill(['waived' => round($invoice->waived + $amount, 2)])->save();

            return $this->recalculate($invoice);
        });
    }

    public function cancel(FeeInvoice $invoice, string $reason, User $user): FeeInvoice
    {
        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages(['reason' => __('This invoice is already cancelled.')]);
        }

        return DB::transaction(function () use ($invoice, $reason, $user) {
            $invoice = $this->recalculate($invoice);
            foreach ($this->ledger->entriesFor($invoice)->filter(fn (JournalEntry $e) => $e->status === 'posted' && ! $e->reversal_of_id) as $entry) {
                $this->ledger->reverse($entry, __('Invoice :invoice cancelled', ['invoice' => $invoice->invoice_no]), $user);
            }

            $paid = 0.0;
            foreach ($invoice->allocations()->with('payment')->get() as $allocation) {
                $allocation->payment->forceFill(['unallocated' => round($allocation->payment->unallocated + $allocation->amount, 2)])->save();
                $paid += $allocation->amount;
                $allocation->delete();
            }
            if ($paid > 0) {
                $this->ledger->post('journal', now(), __('Paid amount of cancelled invoice :invoice kept as advance', ['invoice' => $invoice->invoice_no]), [
                    ['account_id' => $this->chart->id('fees_receivable'), 'debit' => $paid, 'student_id' => $invoice->student_id],
                    ['account_id' => $this->chart->id('student_advance'), 'credit' => $paid, 'student_id' => $invoice->student_id],
                ], $invoice, $user);
            }

            $invoice->forceFill(['status' => 'cancelled', 'paid' => 0, 'balance' => 0, 'cancel_reason' => mb_substr($reason, 0, 255),
                'cancelled_at' => now(), 'cancelled_by' => $user->id])->save();

            // The released credit may settle the student's other open invoices.
            foreach (FeeInvoice::query()->open()->where('student_id', $invoice->student_id)->orderBy('due_date')->orderBy('id')->get() as $other) {
                $this->applyCredit($other, $user);
            }

            return $invoice;
        });
    }

    public function addLateFee(FeeInvoice $invoice, float $amount, ?User $user = null): FeeInvoice
    {
        $amount = round($amount, 2);
        if ($amount <= 0 || ! $invoice->isOpen()) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $amount, $user) {
            $invoice->items()->create(['fee_head_id' => null, 'description' => __('Late fee'), 'amount' => $amount, 'discount' => 0, 'is_late_fee' => true]);
            $invoice->forceFill(['late_fee' => round($invoice->late_fee + $amount, 2), 'total' => round($invoice->total + $amount, 2), 'late_fee_applied_at' => now()])->save();
            $this->ledger->post('invoice', now(), __('Late fee on :invoice', ['invoice' => $invoice->invoice_no]), [
                ['account_id' => $this->chart->id('fees_receivable'), 'debit' => $amount, 'student_id' => $invoice->student_id],
                ['account_id' => $this->chart->id('late_fee'), 'credit' => $amount],
            ], $invoice, $user);

            return $this->recalculate($invoice);
        });
    }

    /** Charge the branch late fee once on every invoice overdue by more than the grace days. Idempotent. */
    public function applyLateFees(?\DateTimeInterface $on = null): int
    {
        $mode = (string) $this->settings->get('late_fee_mode');
        $value = (float) $this->settings->get('late_fee_amount');
        if ($mode === 'none' || $value <= 0) {
            return 0;
        }
        $cutoff = CarbonImmutable::parse($on ?? now())->subDays(max(0, (int) $this->settings->get('late_fee_grace_days')));

        $count = 0;
        foreach (FeeInvoice::query()->open()->whereNull('late_fee_applied_at')->whereDate('due_date', '<', $cutoff->toDateString())->orderBy('id')->get() as $invoice) {
            $amount = $mode === 'percent' ? round($invoice->balance * min(100, $value) / 100, 2) : $value;
            if ($amount > 0) {
                $this->addLateFee($invoice, $amount);
                $count++;
            }
        }

        return $count;
    }

    protected function postIssue(FeeInvoice $invoice, array $items, ?User $user): void
    {
        $heads = FeeHead::query()->whereIn('id', array_filter(array_column($items, 'fee_head_id')))->get()->keyBy('id');
        $credits = [];
        foreach ($items as $item) {
            $account = $item['is_late_fee'] ? $this->chart->id('late_fee')
                : (($item['fee_head_id'] ? $heads->get($item['fee_head_id'])?->income_account_id : null) ?? $this->chart->id('fee_income'));
            $credits[$account] = round(($credits[$account] ?? 0) + $item['amount'], 2);
        }

        $lines = [];
        if ($invoice->total > 0) {
            $lines[] = ['account_id' => $this->chart->id('fees_receivable'), 'debit' => $invoice->total, 'student_id' => $invoice->student_id];
        }
        if ($invoice->discount > 0) {
            $lines[] = ['account_id' => $this->chart->id('fee_discount'), 'debit' => $invoice->discount, 'student_id' => $invoice->student_id, 'description' => __('Discounts & scholarships')];
        }
        foreach ($credits as $account => $amount) {
            $lines[] = ['account_id' => $account, 'credit' => $amount];
        }

        $this->ledger->post('invoice', $invoice->issue_date, __('Invoice :invoice — :title', ['invoice' => $invoice->invoice_no, 'title' => $invoice->title]), $lines, $invoice, $user);
    }

    /** @param Collection<int,StudentEnrollment> $enrollments  → student ids that get the sibling discount (not the eldest-admitted child) */
    protected function siblingsEligible(Collection $enrollments): Collection
    {
        if ((float) $this->settings->get('sibling_discount_percent') <= 0 || $enrollments->isEmpty()) {
            return collect();
        }
        $ids = StudentEnrollment::query()->where('academic_session_id', $enrollments->first()->academic_session_id)->where('status', 'enrolled')->pluck('student_id');
        $pivot = DB::table('guardian_student')->whereIn('student_id', $ids)->get(['guardian_id', 'student_id']);

        $eligible = collect();
        foreach ($pivot->groupBy('guardian_id') as $rows) {
            $students = $rows->pluck('student_id')->unique()->sort()->values();
            foreach ($students->slice(1) as $id) {
                $eligible[(int) $id] = true;
            }
        }

        return $eligible;
    }

    protected function addDiscount(array &$item, float $amount, string $label): float
    {
        $amount = round(max(0, min($amount, $item['amount'] - $item['discount'])), 2);
        if ($amount > 0) {
            $item['discount'] = round($item['discount'] + $amount, 2);
            $item['discount_note'] = trim(($item['discount_note'] ? $item['discount_note'].', ' : '').$label);
        }

        return $amount;
    }

    protected function nextNumber(CarbonImmutable $date): string
    {
        return sprintf('%s-INV%s-%05d', strtoupper($this->tenant->branch()->code), $date->format('ym'), $this->sequences->next('fee_invoice', $date->format('Ym')));
    }
}
