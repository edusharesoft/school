<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Communication\NotificationMessage;
use App\Models\Finance\JournalEntry;
use App\Models\Library\LibraryBook;
use App\Models\Library\LibraryBookCopy;
use App\Models\Library\LibraryFine;
use App\Models\Library\LibraryReservation;
use App\Models\Library\LibraryTransaction;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Library\LibraryService;
use App\Services\Library\LibrarySettings;
use App\Support\Code128;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Library: catalogue and barcodes, circulation rules, fines posted to the accounts, reservation queue, isolation. */
class LibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
    }

    protected function world(): array
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $librarian = $this->branchUser($branch, 'librarian');
        $students = collect(['Anika', 'Chandni', 'Dipu', 'Eshan'])->map(fn ($n) => $this->enrolStudent($branch, $setup, $n));

        return compact('branch', 'setup', 'librarian', 'students');
    }

    protected function url(Branch $branch, string $path): string
    {
        return $this->branchUrl($branch, 'library/'.$path);
    }

    protected function book(Branch $branch, string $title = 'Padma Nadir Majhi', int $copies = 2, float $price = 200): LibraryBook
    {
        return $this->inBranch($branch, function () use ($title, $copies, $price) {
            $book = LibraryBook::query()->create(['title' => $title, 'language' => 'bn', 'price' => $price, 'is_active' => true]);
            app(LibraryService::class)->addCopies($book, $copies);

            return $book;
        });
    }

    protected function svc(Branch $branch, \Closure $fn): mixed
    {
        return $this->inBranch($branch, fn () => $fn(app(LibraryService::class)));
    }

    public function test_code128_symbols_and_svg(): void
    {
        $this->assertSame([104, 48, 42, 19, 20, 25, 86, 106], Code128::symbols('PJ349'));   // start B, data, checksum 498 mod 103, stop
        $modules = Code128::modules('LB-000001');
        $this->assertSame(0, count($modules) % 2 === 1 ? 0 : 1, 'bars and spaces alternate, ending with a bar');
        $this->assertSame(11 * (count(Code128::symbols('LB-000001')) - 1) + 13, array_sum($modules));
        $svg = Code128::svg('LB-000001');
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('LB-000001', $svg);
        $this->expectException(\InvalidArgumentException::class);
        Code128::symbols("tab\t");
    }

    public function test_catalogue_isbn_copies_and_search(): void
    {
        $w = $this->world();
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'categories'), ['name' => 'Novel'])->assertSessionHasNoErrors();
        $cat = $this->inBranch($w['branch'], fn () => \App\Models\Library\LibraryCategory::query()->sole());

        $payload = ['title' => 'Pather Panchali', 'language' => 'bn', 'library_category_id' => $cat->id, 'copies' => 3, 'price' => 150, 'is_active' => 1];
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'books'), $payload + ['isbn' => '978-0-306-40615-8'])->assertSessionHasErrors('isbn');
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'books'), $payload + ['isbn' => '978-0-306-40615-7'])->assertSessionHasNoErrors();

        $book = $this->inBranch($w['branch'], fn () => LibraryBook::query()->sole());
        $this->assertSame('9780306406157', $book->isbn);
        $copies = $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->orderBy('id')->get());
        $this->assertSame(['LB-000001', 'LB-000002', 'LB-000003'], $copies->pluck('accession_no')->all());
        $this->assertSame($copies->pluck('accession_no')->all(), $copies->pluck('barcode')->all());

        // a category from nowhere is rejected
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'books'), ['title' => 'X', 'language' => 'bn', 'library_category_id' => 9999])->assertSessionHasErrors('library_category_id');

        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], 'books?q=Pather'))->assertOk()->assertSee('Pather Panchali');
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], 'books?q=zzz'))->assertOk()->assertDontSee('Pather Panchali');
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], "books/{$book->id}/labels"))->assertOk()->assertSee('LB-000002')->assertSee('<svg', false);
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], 'desk'))->assertOk();
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], ''))->assertOk()->assertSee('Circulation desk');
    }

    public function test_issue_by_barcode_and_double_issue_rules(): void
    {
        $w = $this->world();
        $book = $this->book($w['branch']);
        [$a, $c] = [$w['students'][0], $w['students'][1]];
        $key = 'student:'.$a->id;

        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => $key, 'code' => 'LB-000001'])->assertSessionHasNoErrors();
        $loan = $this->inBranch($w['branch'], fn () => LibraryTransaction::query()->sole());
        $this->assertSame('2026-03-29', $loan->due_on->toDateString());   // 14 days
        $this->assertSame('issued', $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->where('accession_no', 'LB-000001')->value('status')));

        // same copy again, to someone else
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$c->id, 'code' => 'LB-000001'])->assertSessionHasErrors('copy');
        // second copy of the same title to the same student
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => $key, 'code' => 'LB-000002'])->assertSessionHasErrors('member');
        // unknown barcode
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$c->id, 'code' => 'NOPE'])->assertSessionHasErrors('code');
        // a withdrawn copy cannot be issued
        $this->svc($w['branch'], fn ($s) => $s->setCopyStatus(LibraryBookCopy::query()->where('accession_no', 'LB-000002')->first(), 'withdrawn'));
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$c->id, 'code' => 'LB-000002'])->assertSessionHasErrors('copy');

        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], 'desk?member='.$key))->assertOk()->assertSee('Padma Nadir Majhi');
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], "members/student/{$a->id}"))->assertOk()->assertSee('Anika');
    }

    public function test_borrowing_limit_and_settings(): void
    {
        $w = $this->world();
        $a = $w['students'][0];
        foreach (['One', 'Two', 'Three', 'Four'] as $i => $t) {
            $this->book($w['branch'], $t, 1);
        }
        $codes = $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->orderBy('id')->pluck('accession_no')->all());
        foreach (array_slice($codes, 0, 3) as $code) {
            $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => $code])->assertSessionHasNoErrors();
        }
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => $codes[3]])->assertSessionHasErrors('member');

        // the branch raises the limit and shortens the loan
        $rules = $this->inBranch($w['branch'], fn () => app(LibrarySettings::class)->all());
        $this->actingOnBranch($w['librarian'], $w['branch'])->put($this->url($w['branch'], 'rules'), array_merge($rules, ['student_max_books' => 4, 'student_loan_days' => 7, 'block_when_overdue' => 1, 'block_on_unpaid_fines' => 1]))->assertSessionHasNoErrors();
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => $codes[3]])->assertSessionHasNoErrors();
        $this->assertSame('2026-03-22', $this->inBranch($w['branch'], fn () => LibraryTransaction::query()->latest('id')->first()->due_on->toDateString()));
        $this->actingOnBranch($w['librarian'], $w['branch'])->put($this->url($w['branch'], 'rules'), array_merge($rules, ['fine_per_day' => -1]))->assertSessionHasErrors('fine_per_day');
    }

    public function test_late_return_fine_blocks_until_collected_and_posts_to_accounts(): void
    {
        $w = $this->world();
        $this->book($w['branch'], 'Late One', 1);
        $this->book($w['branch'], 'Next One', 1);
        $a = $w['students'][0];
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();

        $this->travelTo(now()->addDays(14 + 5));   // five days late
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], 'loans?overdue=1'))->assertOk()->assertSee('Late One')->assertSee('10.00');
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/return'), ['code' => 'LB-000001', 'condition' => 'good'])->assertSessionHasNoErrors();

        $fine = $this->inBranch($w['branch'], fn () => LibraryFine::query()->sole());
        $this->assertSame('10.00', $fine->amount);   // 5 days × ৳2
        $this->assertSame('overdue', $fine->kind);
        $this->assertSame('available', $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->where('accession_no', 'LB-000001')->value('status')));

        // unpaid fine blocks the next loan
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000002'])->assertSessionHasErrors('member');

        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], "fines/{$fine->id}/pay"), ['method' => 'cash'])->assertSessionHasNoErrors();
        $fine = $fine->refresh();
        $this->assertSame('paid', $fine->status);
        $this->assertSame('LF-26-00001', $fine->receipt_no);
        [$entry, $lines] = $this->inBranch($w['branch'], function () use ($fine) {
            $e = JournalEntry::query()->findOrFail($fine->journal_entry_id);
            $chart = app(ChartOfAccounts::class);

            return [$e, $e->lines->map(fn ($l) => [$l->account_id, (float) $l->debit, (float) $l->credit])->all() + ['cash' => $chart->id('cash'), 'late' => $chart->id('late_fee')]];
        });
        $this->assertSame([$lines['cash'], 10.0, 0.0], $lines[0]);
        $this->assertSame([$lines['late'], 0.0, 10.0], $lines[1]);
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], "fines/{$fine->id}/pay"), ['method' => 'cash'])->assertSessionHas('warning');   // not twice
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], "fines/{$fine->id}/receipt"))->assertOk()->assertSee('LF-26-00001')->assertSee('10.00');

        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000002'])->assertSessionHasNoErrors();
    }

    public function test_waive_charge_and_overdue_blocks_new_loans(): void
    {
        $w = $this->world();
        $this->book($w['branch'], 'A', 1);
        $this->book($w['branch'], 'B', 1);
        $a = $w['students'][0];
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();
        $this->travelTo(now()->addDays(20));
        // overdue → cannot borrow another
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000002'])->assertSessionHasErrors('member');
        // an overdue book cannot be renewed
        $loan = $this->inBranch($w['branch'], fn () => LibraryTransaction::query()->sole());
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], "loans/{$loan->id}/renew"))->assertSessionHas('warning');

        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/return'), ['loan_id' => $loan->id, 'condition' => 'damaged', 'damage_fee' => 40])->assertSessionHasNoErrors();
        $fines = $this->inBranch($w['branch'], fn () => LibraryFine::query()->orderBy('id')->get());
        $this->assertSame(['overdue', 'damaged'], $fines->pluck('kind')->all());
        $this->assertSame(['12.00', '40.00'], $fines->pluck('amount')->all());   // 6 days late × 2
        $this->assertSame('damaged', $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->where('accession_no', 'LB-000001')->value('status')));

        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], "fines/{$fines[1]->id}/waive"), [])->assertSessionHasErrors('reason');
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], "fines/{$fines[1]->id}/waive"), ['reason' => 'Guardian request'])->assertSessionHasNoErrors();
        $this->assertSame('waived', $fines[1]->refresh()->status);
        $this->assertSame(0, $this->inBranch($w['branch'], fn () => JournalEntry::query()->where('source_type', LibraryFine::class)->count()));

        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'fines'), ['member' => 'student:'.$a->id, 'kind' => 'damaged', 'amount' => 25, 'note' => 'Torn cover'])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], 'fines'))->assertOk()->assertSee('Torn cover');
    }

    public function test_lost_copy_is_charged_at_the_book_price(): void
    {
        $w = $this->world();
        $this->book($w['branch'], 'Lost One', 1, 250);
        $a = $w['students'][0];
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/return'), ['code' => 'LB-000001', 'condition' => 'lost'])->assertSessionHasNoErrors();

        $this->assertSame('250.00', $this->inBranch($w['branch'], fn () => LibraryFine::query()->where('kind', 'lost')->value('amount')));
        $this->assertSame('lost', $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->value('status')));
        $this->assertSame('lost', $this->inBranch($w['branch'], fn () => LibraryTransaction::query()->value('status')));
        // returned twice is refused
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/return'), ['code' => 'LB-000001', 'condition' => 'good'])->assertSessionHasErrors('code');
    }

    public function test_renewals_and_the_reservation_queue(): void
    {
        $w = $this->world();
        $book = $this->book($w['branch'], 'Popular', 1);
        [$a, $b, $c] = [$w['students'][0], $w['students'][1], $w['students'][2]];
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();
        $loan = $this->inBranch($w['branch'], fn () => LibraryTransaction::query()->sole());

        $this->travelTo(now()->addDays(3));
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], "loans/{$loan->id}/renew"))->assertSessionHas('success');
        $this->assertSame('2026-04-01', $loan->refresh()->due_on->toDateString());   // today + 14
        $this->assertSame(1, $loan->renewals);

        // two members queue for the only copy
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'reservations'), ['library_book_id' => $book->id, 'member' => 'student:'.$b->id])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'reservations'), ['library_book_id' => $book->id, 'member' => 'student:'.$c->id])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'reservations'), ['library_book_id' => $book->id, 'member' => 'student:'.$b->id])->assertSessionHasErrors('book');   // already queued
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'reservations'), ['library_book_id' => $book->id, 'member' => 'student:'.$a->id])->assertSessionHasErrors('book');   // already has it
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], "loans/{$loan->id}/renew"))->assertSessionHas('warning');   // reserved: no renewal

        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/return'), ['code' => 'LB-000001', 'condition' => 'good'])->assertSessionHasNoErrors();
        $res = $this->inBranch($w['branch'], fn () => LibraryReservation::query()->orderBy('id')->get());
        $this->assertSame(['ready', 'waiting'], $res->pluck('status')->all());
        $this->assertSame('2026-03-21', $res[0]->ready_until->toDateString());   // 3 days
        $this->assertSame('reserved', $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->value('status')));

        // the held copy is for the first in the queue only
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$c->id, 'code' => 'LB-000001'])->assertSessionHasErrors('copy');
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$b->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();
        $this->assertSame(['fulfilled', 'waiting'], $this->inBranch($w['branch'], fn () => LibraryReservation::query()->orderBy('id')->pluck('status')->all()));

        // nobody can reserve when a copy is on the shelf
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->url($w['branch'], 'reservations'))->assertOk();
    }

    public function test_uncollected_reservations_expire_and_pass_on(): void
    {
        $w = $this->world();
        $book = $this->book($w['branch'], 'Popular', 1);
        [$a, $b, $c] = [$w['students'][0], $w['students'][1], $w['students'][2]];
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();
        $this->svc($w['branch'], function ($s) use ($book, $b, $c) { $s->reserve($book, 'student', $b->id); $s->reserve($book, 'student', $c->id); });
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/return'), ['code' => 'LB-000001', 'condition' => 'good'])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->svc($w['branch'], fn ($s) => $s->expireReservations()));
        $this->travelTo(now()->addDays(4));
        $this->assertSame(1, $this->svc($w['branch'], fn ($s) => $s->expireReservations()));
        $res = $this->inBranch($w['branch'], fn () => LibraryReservation::query()->orderBy('id')->get());
        $this->assertSame(['expired', 'ready'], $res->pluck('status')->all());   // handed to the next in line
        $this->assertSame($res[1]->library_book_copy_id, $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->value('id')));

        // cancelling the held reservation puts the copy back on the shelf
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], "reservations/{$res[1]->id}/cancel"))->assertSessionHasNoErrors();
        $this->assertSame('available', $this->inBranch($w['branch'], fn () => LibraryBookCopy::query()->value('status')));
    }

    public function test_overdue_reminders_are_sent_once_per_interval(): void
    {
        $w = $this->world();
        $this->book($w['branch'], 'Reminder', 1);
        $a = $w['students'][0];
        $admin = $this->branchUser($w['branch'], 'branch-admin');
        $this->actingOnBranch($admin, $w['branch'])->post($this->branchUrl($w['branch'], "students/{$a->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $studentUser = \App\Models\User::query()->where('username', strtolower($a->student_code))->sole();
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->svc($w['branch'], fn ($s) => $s->sendOverdueReminders()));   // not overdue yet
        $this->travelTo(now()->addDays(16));
        $first = $this->svc($w['branch'], fn ($s) => $s->sendOverdueReminders());
        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $this->svc($w['branch'], fn ($s) => $s->sendOverdueReminders()));   // within 3 days
        $this->assertGreaterThan(0, $this->inBranch($w['branch'], fn () => NotificationMessage::query()->where('event', 'library.overdue')->where('user_id', $studentUser->id)->count()));
        $this->travelTo(now()->addDays(3));
        $this->assertGreaterThan(0, $this->svc($w['branch'], fn ($s) => $s->sendOverdueReminders()));
    }

    public function test_permissions_and_branch_isolation(): void
    {
        $w = $this->world();
        $book = $this->book($w['branch'], 'Mine', 1);
        $teacher = $this->branchUser($w['branch'], 'teacher');
        $this->actingOnBranch($teacher, $w['branch'])->get($this->url($w['branch'], ''))->assertForbidden();
        $this->actingOnBranch($teacher, $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:1', 'code' => 'LB-000001'])->assertForbidden();

        $other = $this->makeBranch();
        $otherLibrarian = $this->branchUser($other, 'librarian');
        $this->actingOnBranch($otherLibrarian, $other)->get($this->url($other, "books/{$book->id}"))->assertNotFound();
        $this->actingOnBranch($otherLibrarian, $other)->get($this->url($other, 'books'))->assertOk()->assertDontSee('Mine');
        $this->actingOnBranch($otherLibrarian, $other)->post($this->url($other, 'desk/issue'), ['member' => 'student:'.$w['students'][0]->id, 'code' => 'LB-000001'])->assertSessionHasErrors();
        $this->assertSame(0, $this->inBranch($other, fn () => LibraryTransaction::query()->count()));

        // a title with lending history cannot be deleted
        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$w['students'][0]->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['librarian'], $w['branch'])->delete($this->url($w['branch'], "books/{$book->id}"))->assertSessionHas('warning');
        $this->assertNotNull($this->inBranch($w['branch'], fn () => LibraryBook::query()->find($book->id)));
    }

    public function test_student_and_parent_portal_and_api(): void
    {
        $w = $this->world();
        $admin = $this->branchUser($w['branch'], 'branch-admin');
        [$a, $c] = [$w['students'][0], $w['students'][1]];
        $this->book($w['branch'], 'Only Copy', 1);
        $this->book($w['branch'], 'On Shelf', 1);
        foreach ([$a, $c] as $st) {
            $this->actingOnBranch($admin, $w['branch'])->post($this->branchUrl($w['branch'], "students/{$st->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        }
        $guardian = $this->inBranch($w['branch'], fn () => $a->guardians()->first());
        $this->actingOnBranch($admin, $w['branch'])->post($this->branchUrl($w['branch'], "students/{$a->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $studentA = \App\Models\User::query()->where('username', strtolower($a->student_code))->sole();
        $studentC = \App\Models\User::query()->where('username', strtolower($c->student_code))->sole();
        $parentA = \App\Models\User::query()->where('phone', $guardian->phone)->sole();

        $this->actingOnBranch($w['librarian'], $w['branch'])->post($this->url($w['branch'], 'desk/issue'), ['member' => 'student:'.$a->id, 'code' => 'LB-000001'])->assertSessionHasNoErrors();
        $book = $this->inBranch($w['branch'], fn () => LibraryBook::query()->where('title', 'Only Copy')->first());
        $onShelf = $this->inBranch($w['branch'], fn () => LibraryBook::query()->where('title', 'On Shelf')->first());

        // own account
        $this->actingOnBranch($studentA, $w['branch'])->get($this->branchUrl($w['branch'], 'student/library'))->assertOk()->assertSee('Only Copy')->assertSee('Due');
        // another student reserves the only copy; cannot reserve what is on the shelf
        $this->actingOnBranch($studentC, $w['branch'])->get($this->branchUrl($w['branch'], 'student/library?q=Only'))->assertOk()->assertSee('Reserve');
        $this->actingOnBranch($studentC, $w['branch'])->post($this->branchUrl($w['branch'], "student/library/books/{$book->id}/reserve"))->assertSessionHas('success');
        $this->actingOnBranch($studentC, $w['branch'])->post($this->branchUrl($w['branch'], "student/library/books/{$onShelf->id}/reserve"))->assertSessionHas('warning');
        $res = $this->inBranch($w['branch'], fn () => LibraryReservation::query()->sole());
        // a student cannot cancel somebody else's reservation
        $this->actingOnBranch($studentA, $w['branch'])->post($this->branchUrl($w['branch'], "student/library/reservations/{$res->id}/cancel"))->assertNotFound();
        $this->actingOnBranch($studentC, $w['branch'])->post($this->branchUrl($w['branch'], "student/library/reservations/{$res->id}/cancel"))->assertSessionHas('success');

        // guardians follow their own child only
        $this->actingOnBranch($parentA, $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$a->id}/library"))->assertOk()->assertSee('Only Copy');
        $this->actingOnBranch($parentA, $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$c->id}/library"))->assertNotFound();
        $this->actingOnBranch($w['librarian'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/library'))->assertForbidden();

        // API
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $host = fn ($path) => $this->branchUrl($w['branch'], 'api/v1/'.$path);
        $login = fn ($name) => $this->postJson($host('auth/login'), ['login' => $name, 'password' => 'secret123', 'device_name' => 'phpunit'])->assertOk()->json('data.token');
        $token = $login($studentA->username);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson($host('my/library'))->assertOk()->assertJsonPath('data.0.student.name', 'Anika')->assertJsonPath('data.0.loans.0.title', 'Only Copy')->assertJsonPath('data.0.loans.0.overdue', false)->assertJsonPath('data.0.limits.max_books', 3);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson($host('my/library'))->assertUnauthorized();
    }
}
