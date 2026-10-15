<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Finance\FeeStructure;
use App\Models\Payments\OnlinePayment;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FeeInvoiceService;
use App\Services\Payments\BanglaQr\EmvQr;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\PaymentSettings;
use App\Support\Qr\QrEncoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Bangla QR fee payments: EMV payload, QR encoder, scan-and-pay flow, TrxID claim, accounts-office confirmation, statement matching. */
class BanglaQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
        Http::preventStrayRequests();
    }

    /** A static merchant QR as a bank would issue it (account template in tag 26, no amount). */
    protected function staticQr(string $name = 'Demo School', string $city = 'Dhaka'): string
    {
        $account = '0008BDQR.TST0110MERCH12345';
        $body = '000201'.'010211'.'26'.strlen($account).$account.'52045999'.'5303050'.'5802BD'.'59'.sprintf('%02d', strlen($name)).$name.'60'.sprintf('%02d', strlen($city)).$city.'6304';

        return $body.EmvQr::crc16($body);
    }

    protected function fixture(): array
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $student = $this->enrolStudent($branch, $setup, 'Anika');
        $this->inBranch($branch, function () use ($setup) {
            app(ChartOfAccounts::class)->ensure();
            $head = FeeHead::query()->create(['name' => 'Tuition', 'code' => 'TUI', 'category' => 'tuition', 'is_active' => true]);
            FeeStructure::query()->create(['academic_session_id' => $setup['session']->id, 'school_class_id' => $setup['class']->id,
                'fee_head_id' => $head->id, 'amount' => 1000, 'months' => range(1, 12)]);
            app(FeeInvoiceService::class)->generateForPeriod('2026-03');
        });
        $invoice = $this->inBranch($branch, fn () => FeeInvoice::query()->where('student_id', $student->id)->sole());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $guardian = $this->inBranch($branch, fn () => $student->guardians()->first());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $parent = User::query()->where('phone', $guardian->phone)->sole();

        return compact('branch', 'setup', 'admin', 'student', 'invoice', 'parent');
    }

    protected function enableQr(array $f, ?string $text = null): void
    {
        $this->actingOnBranch($f['admin'], $f['branch'])->put($this->branchUrl($f['branch'], 'finance/online-payments/settings'),
            ['banglaqr' => ['enabled' => 1, 'qr_text' => $text ?? $this->staticQr(), 'instructions' => 'Keep the SMS until the receipt arrives.']])->assertSessionHasNoErrors();
    }

    protected function startQr(array $f)
    {
        return $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$f['student']->id}"),
            ['gateway' => 'banglaqr', 'invoice_ids' => [$f['invoice']->id]]);
    }

    protected function attempt(Branch $branch): OnlinePayment
    {
        return $this->inBranch($branch, fn () => OnlinePayment::query()->latest('id')->firstOrFail());
    }

    // ------------------------------------------------------------------ payload and encoder

    public function test_emv_text_is_checked_and_turned_into_a_dynamic_qr_for_one_bill(): void
    {
        $static = $this->staticQr('Demo School', 'Dhaka');
        $info = EmvQr::inspect($static);
        $this->assertSame('Demo School', $info['name']);
        $this->assertSame('Dhaka', $info['city']);
        $this->assertSame(['26'], $info['account_tags']);
        $this->assertFalse($info['has_amount']);

        $this->assertSame('29B1', EmvQr::crc16('123456789'));        // the standard CRC-16/CCITT-FALSE check value

        $dynamic = EmvQr::dynamic($static, 1250.5, 'BR01P2603150000123', 'ST-0001');
        $tags = array_column(EmvQr::parse($dynamic), 1, 0);
        $this->assertSame('12', $tags['01']);                         // dynamic
        $this->assertSame('1250.50', $tags['54']);
        $this->assertSame('050', $tags['53']);
        $this->assertSame('0008BDQR.TST0110MERCH12345', $tags['26']); // the bank's account template is untouched
        $this->assertSame('Demo School', $tags['59']);
        $sub = array_column(EmvQr::parse($tags['62']), 1, 0);
        $this->assertSame('BR01P2603150000123', $sub['01']);
        $this->assertSame('ST-0001', $sub['05']);
        $this->assertSame(EmvQr::crc16(substr($dynamic, 0, -4)), substr($dynamic, -4));
        EmvQr::inspect($dynamic);                                      // still a valid text
        $this->assertSame(['00', '01', '26', '52', '53', '54', '58', '59', '60', '62', '63'], array_column(EmvQr::parse($dynamic), 0));

        foreach ([
            'nonsense text that is long enough to pass the size check but is not a QR payload',
            substr($static, 0, -4).'0000',                              // wrong CRC
            '010211'.substr($static, 6),                                // does not start with 000201
        ] as $bad) {
            try {
                EmvQr::inspect($bad);
                $this->fail('Expected the text to be refused.');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        EmvQr::dynamic($static, 0, 'X');
    }

    public function test_qr_encoder_makes_valid_sized_symbols(): void
    {
        $this->assertCount(21, QrEncoder::matrix('hello'));                        // version 1
        $this->assertCount(29, QrEncoder::matrix(str_repeat('a', 30)));            // version 3
        $payload = EmvQr::dynamic($this->staticQr(), 1000, 'BR01P2603150000123', 'ST0001');
        $size = count(QrEncoder::matrix($payload));
        $this->assertSame(1, $size % 4);
        $this->assertGreaterThan(40, $size);
        // finder patterns sit in three corners
        $m = QrEncoder::matrix($payload);
        $this->assertTrue($m[0][0] && $m[0][6] && $m[6][0] && $m[3][3] && ! $m[1][1]);
        $this->assertTrue($m[0][$size - 1] && $m[$size - 1][0]);
        $svg = QrEncoder::svg($payload, 280);
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0 '.($size + 8).' '.($size + 8).'"', $svg);
        $this->assertSame(QrEncoder::svg($payload), QrEncoder::svg($payload));   // deterministic
        $this->expectException(\InvalidArgumentException::class);
        QrEncoder::matrix(str_repeat('x', 700));
    }

    // ------------------------------------------------------------------ settings

    public function test_the_qr_text_is_validated_and_the_method_is_offered_only_when_it_is_set(): void
    {
        $f = $this->fixture();
        $url = $this->branchUrl($f['branch'], 'finance/online-payments/settings');
        $this->inBranch($f['branch'], fn () => $this->assertSame([], app(PaymentSettings::class)->enabled()));

        $this->actingOnBranch($f['admin'], $f['branch'])->put($url, ['banglaqr' => ['enabled' => 1, 'qr_text' => substr($this->staticQr(), 0, -4).'0000']])->assertSessionHasErrors('banglaqr.qr_text');
        // enabled without a QR text → not offered
        $this->actingOnBranch($f['admin'], $f['branch'])->put($url, ['banglaqr' => ['enabled' => 1, 'qr_text' => '']])->assertSessionHasNoErrors();
        $this->inBranch($f['branch'], fn () => $this->assertSame([], app(PaymentSettings::class)->enabled()));
        $this->startQr($f)->assertSessionHasErrors('gateway');

        $this->enableQr($f);
        $this->inBranch($f['branch'], fn () => $this->assertSame(['banglaqr'], app(PaymentSettings::class)->enabled()));
        $this->actingOnBranch($f['admin'], $f['branch'])->get($url)->assertOk()->assertSee('Saved QR: Demo School, Dhaka');
    }

    // ------------------------------------------------------------------ scan, claim, confirm

    public function test_payer_scans_the_qr_sends_the_trx_id_and_the_accounts_office_accepts_it(): void
    {
        $f = $this->fixture();
        $this->enableQr($f);

        $start = $this->startQr($f);
        $attempt = $this->attempt($f['branch']);
        $start->assertRedirect($this->branchUrl($f['branch'], "online-payments/qr/{$attempt->tran_id}"));
        $this->assertSame('pending', $attempt->status);
        $this->assertSame(1000.0, $attempt->amount);
        $this->assertTrue($attempt->expires_at->isFuture());

        $page = $this->actingOnBranch($f['parent'], $f['branch'])->get($this->branchUrl($f['branch'], "online-payments/qr/{$attempt->tran_id}"));
        $page->assertOk()->assertSee('৳1,000.00')->assertSee('Demo School')->assertSee($attempt->tran_id)->assertSee('<svg', false)->assertSee('Keep the SMS until the receipt arrives.');

        // starting again for the same invoices shows the same QR instead of piling up attempts
        $this->startQr($f)->assertRedirect($this->branchUrl($f['branch'], "online-payments/qr/{$attempt->tran_id}"));
        $this->assertSame(1, $this->inBranch($f['branch'], fn () => OnlinePayment::query()->count()));

        // the payload the app gets carries the bill number
        $payload = $this->inBranch($f['branch'], fn () => app(OnlinePaymentService::class)->qrPayload($attempt->load('student')));
        $this->assertSame('1000.00', array_column(EmvQr::parse($payload), 1, 0)['54']);

        $claimUrl = $this->branchUrl($f['branch'], "online-payments/qr/{$attempt->tran_id}/claim");
        $this->actingOnBranch($f['parent'], $f['branch'])->post($claimUrl, ['trx_id' => 'ab'])->assertSessionHasErrors('trx_id');
        $this->actingOnBranch($f['parent'], $f['branch'])->post($claimUrl, ['trx_id' => '8f3k2l9m0p', 'amount' => 1000, 'payer_account' => '01711000000'])
            ->assertSessionHasNoErrors()->assertRedirect($this->branchUrl($f['branch'], "online-payments/status/{$attempt->tran_id}"));

        $attempt = $attempt->fresh();
        $this->assertSame('review', $attempt->status);
        $this->assertSame('8F3K2L9M0P', $attempt->gateway_txn_id);
        $this->assertSame(1000.0, $attempt->paid_amount);
        $this->assertNull($attempt->fee_payment_id);
        $this->assertSame('pending', $this->inBranch($f['branch'], fn () => $f['invoice']->fresh()->status === 'paid' ? 'paid' : 'pending'));   // nothing booked yet
        $this->actingOnBranch($f['parent'], $f['branch'])->get($this->branchUrl($f['branch'], "online-payments/status/{$attempt->tran_id}"))->assertOk()->assertSee('checking your payment');
        // a second claim for the same attempt is refused (it already sits with the accounts office)
        $this->actingOnBranch($f['parent'], $f['branch'])->post($claimUrl, ['trx_id' => 'ZZZZZZ1234'])->assertSessionHasErrors('trx_id');

        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], "finance/online-payments/{$attempt->id}"))->assertOk()
            ->assertSee('8F3K2L9M0P')->assertSee('statement of the account that receives the QR payments')->assertDontSee('Check with Bangla QR');
        $this->actingOnBranch($f['admin'], $f['branch'])->post($this->branchUrl($f['branch'], "finance/online-payments/{$attempt->id}/review"), ['decision' => 'accept', 'note' => 'Seen in the bank statement'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $attempt->fresh()->status);
        $receipt = $this->inBranch($f['branch'], fn () => FeePayment::query()->sole());
        $this->assertSame(1000.0, (float) $receipt->amount);
        $this->assertSame('paid', $this->inBranch($f['branch'], fn () => $f['invoice']->fresh()->status));
    }

    public function test_a_trx_id_cannot_be_used_twice_and_outsiders_cannot_open_the_qr(): void
    {
        $f = $this->fixture();
        $this->enableQr($f);
        $this->startQr($f);
        $first = $this->attempt($f['branch']);
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/qr/{$first->tran_id}/claim"), ['trx_id' => 'TRX-100001']);
        $this->assertSame('review', $first->fresh()->status);

        // a second attempt (another set of invoices) claiming the same TrxID is refused
        $april = $this->inBranch($f['branch'], function () use ($f) {
            $this->travelTo(now()->setDate(2026, 4, 5));

            return tap(app(FeeInvoiceService::class)->generateForPeriod('2026-04'), fn () => null) ? FeeInvoice::query()->where('student_id', $f['student']->id)->where('id', '!=', $f['invoice']->id)->firstOrFail() : null;
        });
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$f['student']->id}"), ['gateway' => 'banglaqr', 'invoice_ids' => [$april->id]]);
        $second = $this->attempt($f['branch']);
        $this->assertNotSame($first->id, $second->id);
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/qr/{$second->tran_id}/claim"), ['trx_id' => 'trx-100001'])->assertSessionHasErrors('trx_id');
        $this->assertSame('pending', $second->fresh()->status);

        // another student's parent sees nothing; the accounts office cannot use the payer pages
        $other = $this->enrolStudent($f['branch'], $f['setup'], 'Babul');
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$other->id}"), ['gateway' => 'banglaqr', 'invoice_ids' => [$f['invoice']->id]])->assertNotFound();
        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], "online-payments/qr/{$first->tran_id}"))->assertForbidden();
        $stranger = $this->branchUser($f['branch'], 'teacher');
        $this->actingOnBranch($stranger, $f['branch'])->get($this->branchUrl($f['branch'], 'finance/online-payments/statement'))->assertForbidden();
    }

    public function test_an_amount_that_differs_from_the_bill_is_flagged_for_the_office(): void
    {
        $f = $this->fixture();
        $this->enableQr($f);
        $this->startQr($f);
        $attempt = $this->attempt($f['branch']);
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/qr/{$attempt->tran_id}/claim"), ['trx_id' => 'SHORTPAY01', 'amount' => 900])->assertSessionHasNoErrors();
        $attempt = $attempt->fresh();
        $this->assertSame('review', $attempt->status);
        $this->assertSame(900.0, $attempt->paid_amount);
        $this->assertStringContainsString('but ৳1,000.00 was asked', $attempt->failure_reason);
    }

    // ------------------------------------------------------------------ bank statement

    public function test_a_bank_statement_accepts_matching_claims_and_unclaimed_payments_by_reference(): void
    {
        $f = $this->fixture();
        $this->enableQr($f);
        $this->startQr($f);
        $claimed = $this->attempt($f['branch']);
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/qr/{$claimed->tran_id}/claim"), ['trx_id' => 'CLAIM00001'])->assertSessionHasNoErrors();

        $other = $this->enrolStudent($f['branch'], $f['setup'], 'Babul');
        $this->inBranch($f['branch'], fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-03'));
        $babulInvoice = $this->inBranch($f['branch'], fn () => FeeInvoice::query()->where('student_id', $other->id)->firstOrFail());
        $guardian = $this->inBranch($f['branch'], fn () => $other->guardians()->first());
        $this->actingOnBranch($f['admin'], $f['branch'])->post($this->branchUrl($f['branch'], "students/{$other->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $babulParent = User::query()->where('phone', $guardian->phone)->sole();
        $this->actingOnBranch($babulParent, $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$other->id}"), ['gateway' => 'banglaqr', 'invoice_ids' => [$babulInvoice->id]]);
        $unclaimed = $this->attempt($f['branch']);
        $this->assertSame('pending', $unclaimed->status);

        $csv = implode("\n", [
            'Date,TrxID,Amount,Narration',
            "2026-03-15,claim00001,\"1,000.00\",School fee",
            "2026-03-15,BANK778899,1000.00,QR pay {$unclaimed->tran_id}",
            '2026-03-15,SOMEONE001,500.00,Unknown depositor',
            '2026-03-15,BANK000111,250.00,'.$unclaimed->tran_id,   // right reference but the unclaimed one is already taken above (different amount anyway)
        ]);
        $url = $this->branchUrl($f['branch'], 'finance/online-payments/statement');
        $this->actingOnBranch($f['admin'], $f['branch'])->get($url)->assertOk()->assertSee('2 Bangla QR payments are waiting');
        $this->actingOnBranch($f['admin'], $f['branch'])->post($url, ['statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv)])
            ->assertSessionHasNoErrors()->assertRedirect($url);
        $page = $this->actingOnBranch($f['admin'], $f['branch'])->get($url);
        $page->assertOk()->assertSee('4 statement rows were read.')->assertSee('Matched by transaction ID')->assertSee('Matched by payment reference')->assertSee('No payment found');

        $this->assertSame('paid', $claimed->fresh()->status);
        $unclaimed = $unclaimed->fresh();
        $this->assertSame('paid', $unclaimed->status);
        $this->assertSame('BANK778899', $unclaimed->gateway_txn_id);
        $this->assertSame(2, $this->inBranch($f['branch'], fn () => FeePayment::query()->count()));

        // uploading the same statement again changes nothing
        $this->actingOnBranch($f['admin'], $f['branch'])->post($url, ['statement' => UploadedFile::fake()->createWithContent('statement.csv', $csv)])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->inBranch($f['branch'], fn () => FeePayment::query()->count()));

        // a statement without the needed columns is explained, not guessed
        $this->actingOnBranch($f['admin'], $f['branch'])->post($url, ['statement' => UploadedFile::fake()->createWithContent('bad.csv', "Name,Balance\nX,1")])->assertSessionHasErrors('statement');
    }

    public function test_a_statement_row_with_a_different_amount_is_reported_and_not_posted(): void
    {
        $f = $this->fixture();
        $this->enableQr($f);
        $this->startQr($f);
        $attempt = $this->attempt($f['branch']);
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/qr/{$attempt->tran_id}/claim"), ['trx_id' => 'MISMATCH01'])->assertSessionHasNoErrors();
        $rows = [['trx' => 'MISMATCH01', 'amount' => 800.0, 'ref' => ''], ['trx' => 'REFONLY001', 'amount' => 999.0, 'ref' => 'x'.$attempt->tran_id]];
        $result = $this->inBranch($f['branch'], fn () => app(OnlinePaymentService::class)->reconcileStatement($rows, $f['admin']));
        $this->assertSame([], $result['accepted']);
        $this->assertCount(1, $result['mismatch']);                  // the claimed TrxID with another amount
        $this->assertCount(1, $result['unmatched']);                 // the attempt is already claimed, so a reference alone does not match
        $this->assertSame('review', $attempt->fresh()->status);
        $this->assertSame(0, $this->inBranch($f['branch'], fn () => FeePayment::query()->count()));
    }

    // ------------------------------------------------------------------ housekeeping and app

    public function test_unpaid_qr_attempts_expire_without_asking_any_gateway(): void
    {
        $f = $this->fixture();
        $this->enableQr($f);
        $this->startQr($f);
        $attempt = $this->attempt($f['branch']);
        $this->travelTo(now()->addDays(5));
        $stats = $this->inBranch($f['branch'], fn () => app(OnlinePaymentService::class)->reconcile());
        $this->assertGreaterThanOrEqual(1, $stats['expired']);
        $this->assertSame('expired', $attempt->fresh()->status);
        Http::assertNothingSent();

        // an expired attempt can still be claimed (the payer paid late)
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/qr/{$attempt->tran_id}/claim"), ['trx_id' => 'LATEPAY001'])->assertSessionHasNoErrors();
        $this->assertSame('review', $attempt->fresh()->status);
    }

    public function test_mobile_app_gets_the_qr_text_and_reports_the_trx_id(): void
    {
        $f = $this->fixture();
        $this->enableQr($f);
        $host = 'http://'.$f['branch']->subdomain.'.'.config('tenancy.base_domain');
        $token = $this->postJson("$host/api/v1/auth/login", ['login' => $f['parent']->phone, 'password' => 'secret123', 'device_name' => 'app'])->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson("$host/api/v1/my/online-payments/gateways")->assertOk()->assertJsonPath('data.0.key', 'banglaqr')->assertJsonPath('data.0.manual', true);
        $res = $this->withToken($token)->postJson("$host/api/v1/my/online-payments", ['student_id' => $f['student']->id, 'gateway' => 'banglaqr', 'invoice_ids' => [$f['invoice']->id]])
            ->assertCreated()->assertJsonPath('data.redirect_url', null)->assertJsonPath('data.qr.merchant', 'Demo School');
        $tran = $res->json('data.tran_id');
        $payload = $res->json('data.qr.payload');
        $this->assertSame('1000.00', array_column(EmvQr::parse($payload), 1, 0)['54']);
        EmvQr::inspect($payload);

        $this->withToken($token)->postJson("$host/api/v1/my/online-payments/$tran/claim", ['trx_id' => 'x'])->assertUnprocessable();
        $this->withToken($token)->postJson("$host/api/v1/my/online-payments/$tran/claim", ['trx_id' => 'APPTRX0001'])->assertOk()->assertJsonPath('data.status', 'review');
        $this->withToken($token)->getJson("$host/api/v1/my/online-payments/$tran")->assertOk()->assertJsonPath('data.status', 'review')->assertJsonPath('data.gateway_txn_id', 'APPTRX0001');
    }
}
