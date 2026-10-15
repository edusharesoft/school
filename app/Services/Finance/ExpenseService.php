<?php

namespace App\Services\Finance;

use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\User;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Expense vouchers:  pending ──approve──▶ paid      (Dr expense / Cr cash-bank)
 *                            └──approve──▶ approved (Dr expense / Cr accounts payable) ──pay──▶ paid (Dr payable / Cr cash-bank)
 *                    pending ──reject──▶ rejected;   approved | paid ──reverse──▶ reversed (mirror entries)
 * Nobody approves their own request.
 */
class ExpenseService
{
    public function __construct(protected LedgerService $ledger, protected ChartOfAccounts $chart, protected SequenceService $sequences, protected TenantContext $tenant) {}

    public function create(array $data, ?UploadedFile $file, User $user): Expense
    {
        $date = CarbonImmutable::parse($data['date']);
        $this->assertExpenseAccount((int) $data['account_id']);
        if (($data['payment_mode'] ?? 'paid') === 'paid') {
            $this->assertMoneyAccount((int) ($data['paid_from_account_id'] ?? 0));
        } else {
            $data['paid_from_account_id'] = null;
        }

        return DB::transaction(function () use ($data, $file, $user, $date) {
            $expense = new Expense([
                'expense_no' => sprintf('%s-EXP%s-%05d', strtoupper($this->tenant->branch()->code), $date->format('y'), $this->sequences->next('expense', $date->format('Y'))),
                'date' => $date->toDateString(), 'account_id' => (int) $data['account_id'], 'vendor_id' => $data['vendor_id'] ?? null,
                'description' => mb_substr($data['description'], 0, 255), 'amount' => round((float) $data['amount'], 2),
                'payment_mode' => $data['payment_mode'] ?? 'paid', 'paid_from_account_id' => $data['paid_from_account_id'] ?? null,
                'reference' => $data['reference'] ?? null,
            ]);
            if ($file) {
                $expense->attachment_path = $file->store('expenses/'.$this->tenant->branchId(), 'local');
            }
            $expense->forceFill(['status' => 'pending', 'requested_by' => $user->id])->save();

            return $expense;
        });
    }

    public function approve(Expense $expense, User $user, ?string $note = null): Expense
    {
        $this->assertPending($expense);
        if ((int) $expense->requested_by === (int) $user->id) {
            throw ValidationException::withMessages(['status' => __('You cannot approve your own expense.')]);
        }

        return DB::transaction(function () use ($expense, $user, $note) {
            $paid = $expense->payment_mode === 'paid';
            $credit = $paid ? (int) $expense->paid_from_account_id : $this->chart->id('payable');
            $this->ledger->post($paid ? 'expense' : 'journal', $expense->date, __('Expense :no — :text', ['no' => $expense->expense_no, 'text' => $expense->description]), [
                ['account_id' => $expense->account_id, 'debit' => $expense->amount, 'description' => $expense->vendor?->name],
                ['account_id' => $credit, 'credit' => $expense->amount, 'description' => $expense->reference],
            ], $expense, $user);

            $expense->forceFill(['status' => $paid ? 'paid' : 'approved', 'approved_by' => $user->id, 'approved_at' => now(),
                'paid_on' => $paid ? $expense->date : null, 'review_note' => $note])->save();

            return $expense;
        });
    }

    public function pay(Expense $expense, Account $from, \DateTimeInterface|string $date, User $user): Expense
    {
        if ($expense->status !== 'approved') {
            throw ValidationException::withMessages(['status' => __('Only an approved expense bought on credit can be paid.')]);
        }
        $this->assertMoneyAccount($from->id);
        $date = CarbonImmutable::parse($date);

        return DB::transaction(function () use ($expense, $from, $date, $user) {
            $this->ledger->post('payment', $date, __('Payment of :no — :text', ['no' => $expense->expense_no, 'text' => $expense->description]), [
                ['account_id' => $this->chart->id('payable'), 'debit' => $expense->amount, 'description' => $expense->vendor?->name],
                ['account_id' => $from->id, 'credit' => $expense->amount, 'description' => $expense->reference],
            ], $expense, $user);
            $expense->forceFill(['status' => 'paid', 'paid_from_account_id' => $from->id, 'paid_on' => $date->toDateString()])->save();

            return $expense;
        });
    }

    public function reject(Expense $expense, string $note, User $user): Expense
    {
        $this->assertPending($expense);
        $expense->forceFill(['status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'review_note' => mb_substr($note, 0, 255)])->save();

        return $expense;
    }

    public function reverse(Expense $expense, string $reason, User $user): Expense
    {
        if (! in_array($expense->status, ['approved', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => __('Only approved or paid expenses can be reversed.')]);
        }

        return DB::transaction(function () use ($expense, $reason, $user) {
            foreach ($this->ledger->entriesFor($expense)->filter(fn ($e) => $e->status === 'posted' && ! $e->reversal_of_id) as $entry) {
                $this->ledger->reverse($entry, $reason, $user);
            }
            $expense->forceFill(['status' => 'reversed', 'review_note' => mb_substr($reason, 0, 255)])->save();

            return $expense;
        });
    }

    protected function assertPending(Expense $expense): void
    {
        if ($expense->status !== 'pending') {
            throw ValidationException::withMessages(['status' => __('This expense has already been reviewed.')]);
        }
    }

    protected function assertExpenseAccount(int $id): void
    {
        if (! Account::query()->postable()->where('type', 'expense')->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['account_id' => __('Choose an active expense account.')]);
        }
    }

    protected function assertMoneyAccount(int $id): void
    {
        if (! Account::query()->money()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['paid_from_account_id' => __('Choose the cash, bank or mobile-banking account the money was paid from.')]);
        }
    }
}
