<?php

namespace App\Services\Library;

use App\Models\Finance\FeePayment;
use App\Models\HR\Employee;
use App\Models\Library\LibraryBook;
use App\Models\Library\LibraryBookCopy;
use App\Models\Library\LibraryFine;
use App\Models\Library\LibraryReservation;
use App\Models\Library\LibraryTransaction;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\RecipientResolver;
use App\Services\Tenancy\SequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Circulation: issue, renew, return, lost / damaged, reservations queue and fines.
 * Everything that touches a copy runs in one transaction with the copy row locked, so two desks can never
 * issue the same copy. Paid fines are posted to the accounts (Dr cash/bank, Cr "Late fees & fines").
 */
class LibraryService
{
    public function __construct(
        protected TenantContext $tenant,
        protected SequenceService $sequences,
        protected LibrarySettings $settings,
        protected LedgerService $ledger,
        protected ChartOfAccounts $chart,
        protected NotificationHub $hub,
        protected RecipientResolver $recipients,
    ) {}

    // ---------------------------------------------------------------- copies

    /** Add physical copies of a title; accession numbers and barcodes come from a per-branch sequence. @return Collection<int,LibraryBookCopy> */
    public function addCopies(LibraryBook $book, int $count, array $attributes = []): Collection
    {
        $count = max(1, min($count, 200));

        return DB::transaction(function () use ($book, $count, $attributes) {
            $copies = collect();
            for ($i = 0; $i < $count; $i++) {
                $number = sprintf('LB-%06d', $this->sequences->next('library_accession'));
                $copy = new LibraryBookCopy(array_intersect_key($attributes, array_flip(['acquired_on', 'price', 'note'])));
                $copy->forceFill(['library_book_id' => $book->id, 'accession_no' => $number, 'barcode' => $number, 'status' => 'available']);
                $copy->save();
                $copies->push($copy);
            }

            return $copies;
        });
    }

    /** Find a copy by scanned barcode or typed accession number. */
    public function findCopy(string $code): ?LibraryBookCopy
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return LibraryBookCopy::query()->with('book')->where(fn ($q) => $q->where('barcode', $code)->orWhere('accession_no', $code))->first();
    }

    /** Change the status of a copy by hand (withdraw, mark damaged, put back on the shelf). Issued copies are returned through circulation only. */
    public function setCopyStatus(LibraryBookCopy $copy, string $status): LibraryBookCopy
    {
        if (! in_array($status, ['available', 'lost', 'damaged', 'withdrawn'], true)) {
            throw ValidationException::withMessages(['status' => __('Choose available, lost, damaged or withdrawn.')]);
        }

        return DB::transaction(function () use ($copy, $status) {
            $copy = LibraryBookCopy::query()->lockForUpdate()->findOrFail($copy->id);
            if (in_array($copy->status, ['issued', 'reserved'], true)) {
                throw ValidationException::withMessages(['status' => __('This copy is :status — return or cancel the reservation first.', ['status' => __($copy->status)])]);
            }
            $copy->forceFill(['status' => $status])->save();
            if ($status === 'available') {
                $this->promoteReservation($copy);
            }

            return $copy->refresh();
        });
    }

    // ---------------------------------------------------------------- circulation

    public function issue(LibraryBookCopy $copy, string $memberType, int $memberId, User $by, ?string $dueOn = null, ?string $note = null): LibraryTransaction
    {
        $member = LibraryMembers::find($memberType, $memberId) ?? throw ValidationException::withMessages(['member' => __('Member not found.')]);
        if (! LibraryMembers::isActive($member)) {
            throw ValidationException::withMessages(['member' => __('Only active students and staff can borrow books.')]);
        }

        $transaction = DB::transaction(function () use ($copy, $memberType, $memberId, $by, $dueOn, $note) {
            $copy = LibraryBookCopy::query()->lockForUpdate()->with('book')->findOrFail($copy->id);
            $book = $copy->book;
            if (! $book || ! $book->is_active) {
                throw ValidationException::withMessages(['copy' => __('This title is not available for lending.')]);
            }

            $held = LibraryReservation::query()->where('library_book_copy_id', $copy->id)->where('status', 'ready')->first();
            if ($copy->status === 'reserved') {
                if (! $held || $held->member_type !== $memberType || (int) $held->member_id !== $memberId) {
                    throw ValidationException::withMessages(['copy' => __('This copy is being held for another member.')]);
                }
            } elseif ($copy->status !== 'available') {
                throw ValidationException::withMessages(['copy' => __('This copy cannot be issued — it is :status.', ['status' => __($copy->status)])]);
            }

            $this->assertMayBorrow($memberType, $memberId, $book->id);

            $today = Carbon::today();
            $limits = $this->settings->limits($memberType);
            $due = $dueOn ? Carbon::parse($dueOn)->startOfDay() : $today->copy()->addDays($limits['loan_days']);
            if ($due->lt($today) || $due->gt($today->copy()->addDays(366))) {
                throw ValidationException::withMessages(['due_on' => __('The due date must be within the next year.')]);
            }

            $copy->forceFill(['status' => 'issued'])->save();
            LibraryReservation::query()->where('library_book_id', $book->id)->where('member_type', $memberType)->where('member_id', $memberId)
                ->whereIn('status', LibraryReservation::ACTIVE)->update(['status' => 'fulfilled']);

            return LibraryTransaction::query()->create([
                'library_book_copy_id' => $copy->id, 'library_book_id' => $book->id, 'member_type' => $memberType, 'member_id' => $memberId,
                'issued_on' => $today, 'due_on' => $due, 'status' => 'issued', 'issued_by' => $by->id, 'note' => $note,
            ]);
        });

        return $transaction->load('copy', 'book');
    }

    /** Throws when the member may not take another book (limit, overdue, unpaid fines, same title already out). */
    public function assertMayBorrow(string $memberType, int $memberId, ?int $bookId = null): void
    {
        $open = LibraryTransaction::query()->open()->forMember($memberType, $memberId)->get();
        $limits = $this->settings->limits($memberType);
        if ($open->count() >= $limits['max_books']) {
            throw ValidationException::withMessages(['member' => __('Borrowing limit reached: :n book(s) already out.', ['n' => $open->count()])]);
        }
        if ($bookId && $open->contains('library_book_id', $bookId)) {
            throw ValidationException::withMessages(['member' => __('This member already has a copy of this title.')]);
        }
        if ($this->settings->get('block_when_overdue') && $open->contains(fn ($t) => $t->isOverdue())) {
            throw ValidationException::withMessages(['member' => __('This member has overdue books — return them first.')]);
        }
        if ($this->settings->get('block_on_unpaid_fines') && LibraryFine::query()->where('member_type', $memberType)->where('member_id', $memberId)->where('status', 'unpaid')->exists()) {
            throw ValidationException::withMessages(['member' => __('This member has unpaid library fines — collect or waive them first.')]);
        }
    }

    public function renew(LibraryTransaction $transaction): LibraryTransaction
    {
        return DB::transaction(function () use ($transaction) {
            $transaction = LibraryTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->status !== 'issued') {
                throw ValidationException::withMessages(['loan' => __('Only books that are currently out can be renewed.')]);
            }
            if ($transaction->renewals >= (int) $this->settings->get('max_renewals')) {
                throw ValidationException::withMessages(['loan' => __('The renewal limit has been reached.')]);
            }
            if ($transaction->isOverdue()) {
                throw ValidationException::withMessages(['loan' => __('An overdue book must be returned, not renewed.')]);
            }
            if (LibraryReservation::query()->where('library_book_id', $transaction->library_book_id)->whereIn('status', LibraryReservation::ACTIVE)->exists()) {
                throw ValidationException::withMessages(['loan' => __('Someone has reserved this title, so it cannot be renewed.')]);
            }
            $days = $this->settings->limits($transaction->member_type)['loan_days'];
            $transaction->forceFill(['due_on' => Carbon::today()->addDays($days), 'renewals' => $transaction->renewals + 1, 'overdue_notified_on' => null])->save();

            return $transaction;
        });
    }

    /**
     * Take a copy back. $condition: good | damaged | lost. A damaged copy may carry a charge ($damageFee);
     * a lost copy is charged lost_fee_percent of its price. Overdue days are charged in every case.
     *
     * @return array{transaction:LibraryTransaction, fines:Collection<int,LibraryFine>, promoted:?LibraryReservation}
     */
    public function returnCopy(LibraryTransaction $transaction, string $condition, User $by, float $damageFee = 0.0, ?string $returnedOn = null): array
    {
        if (! in_array($condition, ['good', 'damaged', 'lost'], true)) {
            throw ValidationException::withMessages(['condition' => __('Choose good, damaged or lost.')]);
        }

        return DB::transaction(function () use ($transaction, $condition, $by, $damageFee, $returnedOn) {
            $transaction = LibraryTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->status !== 'issued') {
                throw ValidationException::withMessages(['loan' => __('This book has already been returned.')]);
            }
            $copy = LibraryBookCopy::query()->lockForUpdate()->with('book')->findOrFail($transaction->library_book_copy_id);
            $on = $returnedOn ? Carbon::parse($returnedOn)->startOfDay() : Carbon::today();
            if ($on->lt($transaction->issued_on) || $on->gt(Carbon::today())) {
                throw ValidationException::withMessages(['returned_on' => __('The return date cannot be before the issue date or in the future.')]);
            }

            $fines = collect();
            $overdue = $this->overdueAmount($transaction->due_on, $on);
            if ($overdue > 0) {
                $fines->push($this->fine($transaction, 'overdue', $overdue, __(':n day(s) late', ['n' => $this->lateDays($transaction->due_on, $on)]), $by));
            }
            if ($condition === 'lost') {
                $lost = round($copy->value() * (float) $this->settings->get('lost_fee_percent') / 100, 2);
                if ($lost > 0) {
                    $fines->push($this->fine($transaction, 'lost', $lost, __('Lost copy :no', ['no' => $copy->accession_no]), $by));
                }
            } elseif ($condition === 'damaged' && $damageFee > 0) {
                $fines->push($this->fine($transaction, 'damaged', round($damageFee, 2), __('Damaged copy :no', ['no' => $copy->accession_no]), $by));
            }

            $transaction->forceFill([
                'status' => $condition === 'lost' ? 'lost' : 'returned', 'returned_on' => $on, 'return_condition' => $condition, 'returned_by' => $by->id,
            ])->save();
            $copy->forceFill(['status' => match ($condition) { 'lost' => 'lost', 'damaged' => 'damaged', default => 'available' }])->save();

            $promoted = $condition === 'good' ? $this->promoteReservation($copy) : null;

            return ['transaction' => $transaction->refresh(), 'fines' => $fines, 'promoted' => $promoted];
        });
    }

    public function lateDays(\DateTimeInterface $due, \DateTimeInterface $on): int
    {
        $days = (int) Carbon::parse($due)->startOfDay()->diffInDays(Carbon::parse($on)->startOfDay(), false);

        return max(0, $days - (int) $this->settings->get('grace_days'));
    }

    public function overdueAmount(\DateTimeInterface $due, \DateTimeInterface $on): float
    {
        $amount = $this->lateDays($due, $on) * (float) $this->settings->get('fine_per_day');
        $cap = (float) $this->settings->get('max_fine_per_loan');

        return round($cap > 0 ? min($amount, $cap) : $amount, 2);
    }

    /** The fine the member would owe if the book came back today. */
    public function accruedFine(LibraryTransaction $transaction): float
    {
        return $transaction->status === 'issued' ? $this->overdueAmount($transaction->due_on, Carbon::today()) : 0.0;
    }

    // ---------------------------------------------------------------- reservations

    public function reserve(LibraryBook $book, string $memberType, int $memberId): LibraryReservation
    {
        $member = LibraryMembers::find($memberType, $memberId) ?? throw ValidationException::withMessages(['member' => __('Member not found.')]);
        if (! LibraryMembers::isActive($member)) {
            throw ValidationException::withMessages(['member' => __('Only active students and staff can reserve books.')]);
        }

        return DB::transaction(function () use ($book, $memberType, $memberId) {
            $book = LibraryBook::query()->lockForUpdate()->findOrFail($book->id);
            if (! $book->is_active) {
                throw ValidationException::withMessages(['book' => __('This title is not available for lending.')]);
            }
            $copies = $book->copies()->get();
            if ($copies->contains('status', 'available')) {
                throw ValidationException::withMessages(['book' => __('A copy is on the shelf — borrow it instead of reserving.')]);
            }
            if (! $copies->whereIn('status', ['issued', 'reserved'])->count()) {
                throw ValidationException::withMessages(['book' => __('No copy of this title can be borrowed at the moment.')]);
            }
            if (LibraryTransaction::query()->open()->forMember($memberType, $memberId)->where('library_book_id', $book->id)->exists()) {
                throw ValidationException::withMessages(['book' => __('This member already has a copy of this title.')]);
            }
            if (LibraryReservation::query()->where('library_book_id', $book->id)->where('member_type', $memberType)->where('member_id', $memberId)->whereIn('status', LibraryReservation::ACTIVE)->exists()) {
                throw ValidationException::withMessages(['book' => __('This member is already in the queue for this title.')]);
            }

            return LibraryReservation::query()->create([
                'library_book_id' => $book->id, 'member_type' => $memberType, 'member_id' => $memberId, 'status' => 'waiting', 'reserved_on' => Carbon::today(),
            ]);
        });
    }

    public function cancelReservation(LibraryReservation $reservation): LibraryReservation
    {
        return DB::transaction(function () use ($reservation) {
            $reservation = LibraryReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if (! $reservation->isActive()) {
                throw ValidationException::withMessages(['reservation' => __('This reservation is no longer active.')]);
            }
            $heldCopy = $reservation->status === 'ready' ? $reservation->library_book_copy_id : null;
            $reservation->forceFill(['status' => 'cancelled'])->save();
            $this->releaseHeldCopy($heldCopy);

            return $reservation;
        });
    }

    /** Release copies held past their date and hand them to the next in the queue. @return int reservations expired */
    public function expireReservations(?Carbon $today = null): int
    {
        $today ??= Carbon::today();
        $count = 0;
        LibraryReservation::query()->where('status', 'ready')->whereDate('ready_until', '<', $today->toDateString())->get()->each(function ($r) use (&$count) {
            DB::transaction(function () use ($r, &$count) {
                $r->forceFill(['status' => 'expired'])->save();
                $this->releaseHeldCopy($r->library_book_copy_id);
                $count++;
            });
        });

        return $count;
    }

    /** A copy came back (or was freed): reserve it for the first member waiting for the title, otherwise put it on the shelf. */
    protected function promoteReservation(LibraryBookCopy $copy): ?LibraryReservation
    {
        $copy->forceFill(['status' => 'available'])->save();
        $next = LibraryReservation::query()->where('library_book_id', $copy->library_book_id)->where('status', 'waiting')->orderBy('id')->lockForUpdate()->first();
        if (! $next) {
            return null;
        }
        $until = Carbon::today()->addDays((int) $this->settings->get('reservation_hold_days'));
        $next->forceFill(['status' => 'ready', 'library_book_copy_id' => $copy->id, 'ready_until' => $until])->save();
        $copy->forceFill(['status' => 'reserved'])->save();
        $this->notifyReady($next, $copy);

        return $next;
    }

    protected function releaseHeldCopy(?int $copyId): void
    {
        if ($copyId && ($copy = LibraryBookCopy::query()->lockForUpdate()->find($copyId)) && $copy->status === 'reserved') {
            $this->promoteReservation($copy);
        }
    }

    // ---------------------------------------------------------------- fines

    protected function fine(LibraryTransaction $transaction, string $kind, float $amount, ?string $note, User $by): LibraryFine
    {
        return LibraryFine::query()->create([
            'library_transaction_id' => $transaction->id, 'member_type' => $transaction->member_type, 'member_id' => $transaction->member_id,
            'kind' => $kind, 'amount' => $amount, 'status' => 'unpaid', 'note' => $note, 'created_by' => $by->id,
        ]);
    }

    /** Charge a fine that is not tied to a return (for example a damage found later). */
    public function charge(string $memberType, int $memberId, string $kind, float $amount, ?string $note, User $by): LibraryFine
    {
        if (! LibraryMembers::find($memberType, $memberId)) {
            throw ValidationException::withMessages(['member' => __('Member not found.')]);
        }
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be greater than zero.')]);
        }

        return LibraryFine::query()->create([
            'member_type' => $memberType, 'member_id' => $memberId, 'kind' => $kind, 'amount' => round($amount, 2), 'status' => 'unpaid', 'note' => $note, 'created_by' => $by->id,
        ]);
    }

    public function payFine(LibraryFine $fine, string $method, User $by, ?string $paidOn = null): LibraryFine
    {
        if (! in_array($method, FeePayment::METHODS, true)) {
            throw ValidationException::withMessages(['method' => __('Choose a payment method.')]);
        }

        return DB::transaction(function () use ($fine, $method, $by, $paidOn) {
            $fine = LibraryFine::query()->lockForUpdate()->findOrFail($fine->id);
            if ($fine->status !== 'unpaid') {
                throw ValidationException::withMessages(['fine' => __('This fine is already settled.')]);
            }
            $date = $paidOn ? Carbon::parse($paidOn)->startOfDay() : Carbon::today();
            if ($date->gt(Carbon::today())) {
                throw ValidationException::withMessages(['paid_on' => __('The date cannot be in the future.')]);
            }
            $receipt = sprintf('LF-%s-%05d', $date->format('y'), $this->sequences->next('library_fine_receipt', $date->format('Y')));
            $account = $this->chart->forMethod($method);
            $entry = $this->ledger->post('receipt', $date, __('Library fine :receipt', ['receipt' => $receipt]), [
                ['account_id' => $account->id, 'debit' => (float) $fine->amount, 'description' => __('Library :kind fine', ['kind' => __($fine->kind)])],
                ['account_id' => $this->chart->id('late_fee'), 'credit' => (float) $fine->amount, 'description' => __('Library :kind fine', ['kind' => __($fine->kind)])],
            ], $fine, $by);
            $fine->forceFill(['status' => 'paid', 'receipt_no' => $receipt, 'paid_on' => $date, 'method' => $method, 'account_id' => $account->id, 'journal_entry_id' => $entry->id, 'settled_by' => $by->id])->save();

            return $fine;
        });
    }

    public function waiveFine(LibraryFine $fine, string $reason, User $by): LibraryFine
    {
        return DB::transaction(function () use ($fine, $reason, $by) {
            $fine = LibraryFine::query()->lockForUpdate()->findOrFail($fine->id);
            if ($fine->status !== 'unpaid') {
                throw ValidationException::withMessages(['fine' => __('This fine is already settled.')]);
            }
            $fine->forceFill(['status' => 'waived', 'note' => trim(($fine->note ? $fine->note.' — ' : '').__('Waived').': '.$reason), 'settled_by' => $by->id, 'paid_on' => Carbon::today()])->save();

            return $fine;
        });
    }

    // ---------------------------------------------------------------- reminders & summaries

    /** Overdue reminders, repeated at most every overdue_reminder_days. @return int notices created */
    public function sendOverdueReminders(?Carbon $today = null): int
    {
        $today ??= Carbon::today();
        $every = max(1, (int) $this->settings->get('overdue_reminder_days'));
        $sent = 0;
        $loans = LibraryTransaction::query()->overdue($today)->with('book:id,title,title_bn')
            ->where(fn ($q) => $q->whereNull('overdue_notified_on')->orWhereDate('overdue_notified_on', '<=', $today->copy()->subDays($every)->toDateString()))->get();
        LibraryMembers::attach($loans);

        foreach ($loans as $loan) {
            $member = $loan->memberModel();
            if (! $member || ! LibraryMembers::isActive($member)) {
                continue;
            }
            $vars = [
                'title' => $loan->book?->displayTitle(), 'due_date' => $loan->due_on->format('d/m/Y'), 'days' => $this->lateDays($loan->due_on, $today),
                'fine' => number_format($this->overdueAmount($loan->due_on, $today), 2), 'student_name' => $member->name,
            ];
            $stats = $this->hub->notify('library.overdue', $this->recipientsFor($member, $vars), $vars, $loan, ['dedupe_suffix' => $today->toDateString()]);
            $loan->forceFill(['overdue_notified_on' => $today])->save();
            $sent += $stats['created'];
        }

        return $sent;
    }

    protected function notifyReady(LibraryReservation $reservation, LibraryBookCopy $copy): void
    {
        try {
            $member = LibraryMembers::find($reservation->member_type, (int) $reservation->member_id);
            $book = LibraryBook::query()->find($reservation->library_book_id);
            if (! $member || ! $book) {
                return;
            }
            $vars = ['title' => $book->displayTitle(), 'until' => $reservation->ready_until->format('d/m/Y'), 'student_name' => $member->name];
            $this->hub->notify('library.reservation_ready', $this->recipientsFor($member, $vars), $vars, $reservation);
        } catch (\Throwable $e) {
            report($e);   // a failed message must never undo a return
        }
    }

    protected function recipientsFor(Student|Employee $member, array $vars): Collection
    {
        if ($member instanceof Employee) {
            return collect([$this->recipients->employee($member)->with($vars)]);
        }

        return collect([$this->recipients->student($member, $vars)])->merge($this->recipients->guardiansOf($member, $vars)->values());
    }

    /** Everything a member has with the library. */
    public function account(string $memberType, int $memberId): array
    {
        $loans = LibraryTransaction::query()->forMember($memberType, $memberId)->with('book:id,title,title_bn,authors', 'copy:id,accession_no')->latest('id')->limit(100)->get();

        return [
            'open' => $loans->where('status', 'issued')->values(),
            'history' => $loans->where('status', '!=', 'issued')->values(),
            'reservations' => LibraryReservation::query()->where('member_type', $memberType)->where('member_id', $memberId)->whereIn('status', LibraryReservation::ACTIVE)->with('book:id,title,title_bn')->orderBy('id')->get(),
            'fines' => LibraryFine::query()->where('member_type', $memberType)->where('member_id', $memberId)->latest('id')->limit(50)->get(),
            'unpaid' => (float) LibraryFine::query()->where('member_type', $memberType)->where('member_id', $memberId)->where('status', 'unpaid')->sum('amount'),
            'limits' => $this->settings->limits($memberType),
        ];
    }
}
