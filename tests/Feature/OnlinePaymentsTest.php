<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Finance\FeeStructure;
use App\Models\Payments\OnlinePayment;
use App\Models\Payments\OnlinePaymentEvent;
use App\Models\Students\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FeeInvoiceService;
use App\Services\Payments\PaymentSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OnlinePaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
        Http::preventStrayRequests();
    }

    /** Branch with class Six (tuition 1000 / month), student Anika with a March invoice of 1000 and parent + student logins. */
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
        $studentUser = User::query()->where('username', strtolower($student->student_code))->sole();

        return compact('branch', 'setup', 'admin', 'student', 'invoice', 'parent', 'studentUser');
    }

    protected function enable(Branch $branch, string $gateway, array $config, array $secrets): void
    {
        $this->inBranch($branch, function () use ($gateway, $config, $secrets) {
            $settings = app(PaymentSettings::class);
            $settings->set($gateway.'_enabled', true);
            $settings->set($gateway.'_sandbox', true);
            $settings->set($gateway.'_config', $config);
            $settings->set($gateway.'_secrets', $secrets, true);
        });
    }

    protected function start(array $f, string $gateway, ?User $as = null)
    {
        return $this->actingOnBranch($as ?? $f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$f['student']->id}"), [
            'gateway' => $gateway, 'invoice_ids' => [$f['invoice']->id],
        ]);
    }

    protected function attempt(Branch $branch): OnlinePayment
    {
        return $this->inBranch($branch, fn () => OnlinePayment::query()->latest('id')->firstOrFail());
    }

    protected function receipts(Branch $branch)
    {
        return $this->inBranch($branch, fn () => FeePayment::query()->orderBy('id')->get());
    }

    protected function returnUrl(Branch $branch, string $gateway, string $tran, string $result = 'success'): string
    {
        return $this->branchUrl($branch, "online-payments/$gateway/return/$tran/$result");
    }

    /** Signed SSLCOMMERZ IPN body (verify_key lists the signed fields; the store password enters as its md5). */
    protected function sslIpn(array $fields, string $storePassword): array
    {
        $fields['verify_key'] = implode(',', array_keys($fields));
        $data = $fields;
        unset($data['verify_key']);
        $data['store_passwd'] = md5($storePassword);
        ksort($data);
        $fields['verify_sign'] = md5(implode('&', array_map(fn ($k, $v) => "$k=$v", array_keys($data), $data)));

        return $fields;
    }

    public function test_sslcommerz_payment_is_posted_only_after_server_validation_and_only_once(): void
    {
        $f = $this->fixture();
        $this->enable($f['branch'], 'sslcommerz', ['store_id' => 'testbox'], ['store_passwd' => 'qwerty']);
        $validated = ['status' => 'VALID', 'tran_id' => null, 'val_id' => 'VAL123', 'amount' => '1000.00', 'currency_type' => 'BDT', 'currency_amount' => '1000.00',
            'bank_tran_id' => 'BANK777', 'card_type' => 'BKASH-BKash', 'risk_level' => '0'];
        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/EasyCheckOut/abc', 'sessionkey' => 'SESS1']),
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => function (HttpRequest $r) use (&$validated) {
                return Http::response($validated);
            },
        ]);

        // The portal offers the gateway and the open invoice.
        $this->actingOnBranch($f['parent'], $f['branch'])->get($this->branchUrl($f['branch'], "parent/children/{$f['student']->id}/fees"))
            ->assertOk()->assertSee('Pay online')->assertSee('SSLCOMMERZ')->assertSee($f['invoice']->invoice_no);

        $this->start($f, 'sslcommerz')->assertRedirect('https://sandbox.sslcommerz.com/EasyCheckOut/abc');
        $attempt = $this->attempt($f['branch']);
        $this->assertSame('initiated', $attempt->status);
        $this->assertSame(1000.0, $attempt->amount);
        $this->assertSame('SESS1', $attempt->gateway_ref);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+P260315\d{6}$/', $attempt->tran_id);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'gwprocess') && $r['tran_id'] === $attempt->tran_id && $r['total_amount'] === '1000.00'
            && $r['store_id'] === 'testbox' && str_ends_with($r['success_url'], "/online-payments/sslcommerz/return/{$attempt->tran_id}/success"));
        $validated['tran_id'] = $attempt->tran_id;

        // The payer comes back with a cross-site POST (no session); the val_id is checked with SSLCOMMERZ first.
        $this->post($this->returnUrl($f['branch'], 'sslcommerz', $attempt->tran_id), ['tran_id' => $attempt->tran_id, 'val_id' => 'VAL123', 'status' => 'VALID'])
            ->assertRedirect($this->branchUrl($f['branch'], "online-payments/status/{$attempt->tran_id}"));
        $attempt->refresh();
        $this->assertSame('paid', $attempt->status);
        $this->assertSame('BANK777', $attempt->gateway_txn_id);
        $receipt = $this->receipts($f['branch'])->sole();
        $this->assertSame(1000.0, $receipt->amount);
        $this->assertSame('online', $receipt->method);
        $this->assertSame($receipt->id, $attempt->fee_payment_id);
        $this->assertSame('paid', $f['invoice']->fresh()->status);

        // Repeated return and a valid IPN change nothing.
        $this->post($this->returnUrl($f['branch'], 'sslcommerz', $attempt->tran_id), ['tran_id' => $attempt->tran_id, 'val_id' => 'VAL123']);
        $ipn = $this->sslIpn(['tran_id' => $attempt->tran_id, 'val_id' => 'VAL123', 'amount' => '1000.00', 'status' => 'VALID'], 'qwerty');
        $this->post($this->branchUrl($f['branch'], 'online-payments/sslcommerz/ipn'), $ipn)->assertOk();
        $this->assertCount(1, $this->receipts($f['branch']));

        // A forged IPN is refused.
        $this->post($this->branchUrl($f['branch'], 'online-payments/sslcommerz/ipn'), ['verify_sign' => 'x'] + $ipn)->assertForbidden();

        $this->actingOnBranch($f['parent'], $f['branch'])->get($this->branchUrl($f['branch'], "online-payments/status/{$attempt->tran_id}"))
            ->assertOk()->assertSee('Payment received')->assertSee($receipt->receipt_no, false);
        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], 'finance/online-payments'))->assertOk()->assertSee($attempt->tran_id);
        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], "finance/online-payments/{$attempt->id}"))->assertOk()
            ->assertSee('BANK777')->assertDontSee('qwerty');
        $this->assertStringNotContainsString('qwerty', json_encode($this->inBranch($f['branch'], fn () => OnlinePaymentEvent::query()->pluck('payload'))));
    }

    public function test_a_success_page_alone_never_marks_an_invoice_paid(): void
    {
        $f = $this->fixture();
        $this->enable($f['branch'], 'sslcommerz', ['store_id' => 'testbox'], ['store_passwd' => 'qwerty']);
        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay', 'sessionkey' => 'S']),
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => Http::response(['status' => 'INVALID_TRANSACTION']),
            'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => Http::response(['APIConnect' => 'DONE', 'no_of_trans_found' => 0, 'element' => []]),
        ]);
        $this->start($f, 'sslcommerz');
        $attempt = $this->attempt($f['branch']);

        $this->post($this->returnUrl($f['branch'], 'sslcommerz', $attempt->tran_id), ['tran_id' => $attempt->tran_id, 'val_id' => 'FAKE', 'status' => 'VALID', 'amount' => '1000']);
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertCount(0, $this->receipts($f['branch']));
        $this->assertSame('unpaid', $f['invoice']->fresh()->status);

        // Unknown transaction ids are logged and ignored.
        $this->get($this->returnUrl($f['branch'], 'sslcommerz', 'NOSUCHTRAN'))->assertRedirect();
        $this->assertSame(1, $this->inBranch($f['branch'], fn () => OnlinePaymentEvent::query()->where('result', 'unknown transaction')->count()));

        // Browser cancel with nothing at the gateway: cancelled, not paid.
        $this->start($f, 'sslcommerz');
        $second = $this->attempt($f['branch']);
        $this->get($this->returnUrl($f['branch'], 'sslcommerz', $second->tran_id, 'cancel'));
        $this->assertSame('cancelled', $second->fresh()->status);
    }

    public function test_amount_mismatch_is_held_for_review_and_can_be_accepted(): void
    {
        $f = $this->fixture();
        $this->enable($f['branch'], 'sslcommerz', ['store_id' => 'testbox'], ['store_passwd' => 'qwerty']);
        $tran = null;
        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay', 'sessionkey' => 'S']),
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => function () use (&$tran) {
                return Http::response(['status' => 'VALID', 'tran_id' => $tran, 'amount' => '900.00', 'currency_type' => 'BDT', 'currency_amount' => '900.00', 'bank_tran_id' => 'B900']);
            },
        ]);
        $this->start($f, 'sslcommerz');
        $attempt = $this->attempt($f['branch']);
        $tran = $attempt->tran_id;

        $this->post($this->returnUrl($f['branch'], 'sslcommerz', $tran), ['tran_id' => $tran, 'val_id' => 'V']);
        $attempt->refresh();
        $this->assertSame('review', $attempt->status);
        $this->assertStringContainsString('900.00', $attempt->failure_reason);
        $this->assertCount(0, $this->receipts($f['branch']));
        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], 'finance/online-payments'))->assertOk()->assertSee('need');

        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], "finance/online-payments/{$attempt->id}"))->assertOk()
            ->assertSee('Accept and post receipt');
        $this->actingOnBranch($f['admin'], $f['branch'])->post($this->branchUrl($f['branch'], "finance/online-payments/{$attempt->id}/review"), [
            'decision' => 'accept', 'note' => 'Checked in merchant panel, payer paid 900',
        ])->assertSessionHasNoErrors();
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertSame(900.0, $this->receipts($f['branch'])->sole()->amount);
        $this->assertSame('partial', $f['invoice']->fresh()->status);
    }

    public function test_bkash_payment_is_executed_and_can_be_refunded(): void
    {
        $f = $this->fixture();
        $this->enable($f['branch'], 'bkash', ['username' => 'sandboxTokenizedUser02', 'app_key' => 'key1'], ['password' => 'pw', 'app_secret' => 'sec']);
        $base = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout';
        $tran = null;
        Http::fake([
            "$base/token/grant" => Http::response(['statusCode' => '0000', 'id_token' => 'TOKEN1', 'expires_in' => 3600]),
            "$base/create" => Http::response(['statusCode' => '0000', 'paymentID' => 'PAY1', 'bkashURL' => 'https://sandbox.payment.bkash.com/?paymentId=PAY1']),
            "$base/execute" => function () use (&$tran) {
                return Http::response(['statusCode' => '0000', 'paymentID' => 'PAY1', 'trxID' => 'TRX1', 'transactionStatus' => 'Completed', 'amount' => '1000.00', 'currency' => 'BDT', 'merchantInvoiceNumber' => $tran]);
            },
            "$base/payment/refund" => Http::response(['completedTime' => '2026-03-15T10:10:00', 'transactionStatus' => 'Completed', 'originalTrxID' => 'TRX1', 'refundTrxID' => 'RFD1', 'amount' => '1000.00', 'currency' => 'BDT']),
        ]);

        $this->start($f, 'bkash', $f['studentUser'])->assertRedirect('https://sandbox.payment.bkash.com/?paymentId=PAY1');
        $attempt = $this->attempt($f['branch']);
        $tran = $attempt->tran_id;
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/create') && $r->header('Authorization')[0] === 'TOKEN1' && $r->header('X-APP-Key')[0] === 'key1'
            && $r['merchantInvoiceNumber'] === $tran && $r['amount'] === '1000.00' && $r['mode'] === '0011');

        $this->get($this->returnUrl($f['branch'], 'bkash', $tran).'?paymentID=PAY1&status=success&signature=x')->assertRedirect();
        $attempt->refresh();
        $this->assertSame('paid', $attempt->status);
        $this->assertSame('TRX1', $attempt->gateway_txn_id);
        $receipt = $this->receipts($f['branch'])->sole();
        $this->assertSame('bkash', $receipt->method);

        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], "finance/online-payments/{$attempt->id}"))->assertOk()
            ->assertSee('Refund ৳1,000.00')->assertSee('TRX1');
        $this->actingOnBranch($f['admin'], $f['branch'])->post($this->branchUrl($f['branch'], "finance/online-payments/{$attempt->id}/refund"), ['reason' => 'Paid twice by mistake'])
            ->assertSessionHasNoErrors();
        $attempt->refresh();
        $this->assertSame('refunded', $attempt->status);
        $this->assertSame('RFD1', $attempt->refund_ref);
        $this->assertTrue($receipt->fresh()->isReversed());
        $this->assertSame('unpaid', $f['invoice']->fresh()->status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/payment/refund') && $r['trxID'] === 'TRX1' && $r['paymentID'] === 'PAY1');
    }

    public function test_shurjopay_and_aamarpay_results_come_from_their_verification_apis(): void
    {
        $f = $this->fixture();
        $this->enable($f['branch'], 'shurjopay', ['username' => 'sp_sandbox', 'prefix' => 'SCH'], ['password' => 'pw']);
        $this->enable($f['branch'], 'aamarpay', ['store_id' => 'aamarpaytest'], ['signature_key' => 'sig']);
        $trans = [];
        Http::fake([
            'https://sandbox.shurjopayment.com/api/get_token' => Http::response(['token' => 'SPT', 'store_id' => 1, 'sp_code' => '200']),
            'https://sandbox.shurjopayment.com/api/secret-pay' => Http::response(['checkout_url' => 'https://sandbox.securepay.shurjopayment.com/spaycheckout/?token=1', 'sp_order_id' => 'SP-1']),
            'https://sandbox.shurjopayment.com/api/verification' => function () use (&$trans) {
                return Http::response([['order_id' => 'SP-1', 'customer_order_id' => $trans['shurjopay'], 'sp_code' => '1000', 'sp_message' => 'Success',
                    'amount' => '1000.00', 'received_amount' => '1000.00', 'currency' => 'BDT', 'bank_trx_id' => 'SPBANK', 'method' => 'Nagad']]);
            },
            'https://sandbox.aamarpay.com/jsonpost.php' => Http::response(['result' => 'true', 'payment_url' => 'https://sandbox.aamarpay.com/paynow.php?track=1']),
            'https://sandbox.aamarpay.com/api/v1/trxcheck/request.php*' => Http::response(['pay_status' => 'Failed', 'status_code' => '7', 'mer_txnid' => 'x']),
        ]);

        $this->start($f, 'shurjopay')->assertRedirect('https://sandbox.securepay.shurjopayment.com/spaycheckout/?token=1');
        $sp = $this->attempt($f['branch']);
        $trans['shurjopay'] = $sp->tran_id;
        $this->get($this->returnUrl($f['branch'], 'shurjopay', $sp->tran_id).'?order_id=SP-1');
        $this->assertSame('paid', $sp->fresh()->status);
        $this->assertSame('SPBANK', $sp->fresh()->gateway_txn_id);
        $this->assertCount(1, $this->receipts($f['branch']));

        // aamarPay: the invoice is already paid, so it is no longer offered; make a new one.
        $this->inBranch($f['branch'], fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-04'));
        $april = $this->inBranch($f['branch'], fn () => FeeInvoice::query()->where('student_id', $f['student']->id)->open()->sole());
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$f['student']->id}"), ['gateway' => 'aamarpay', 'invoice_ids' => [$april->id]])
            ->assertRedirect('https://sandbox.aamarpay.com/paynow.php?track=1');
        $ap = $this->attempt($f['branch']);
        $this->post($this->returnUrl($f['branch'], 'aamarpay', $ap->tran_id, 'fail'), ['mer_txnid' => $ap->tran_id, 'pay_status' => 'Successful']);
        $this->assertSame('failed', $ap->fresh()->status);
        $this->assertCount(1, $this->receipts($f['branch']));

        // Paying an invoice that is already settled is refused.
        $this->start($f, 'shurjopay')->assertSessionHasErrors('invoice_ids');
    }

    public function test_nagad_request_is_encrypted_and_signed_and_the_answer_verified(): void
    {
        $f = $this->fixture();
        $merchant = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $nagad = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($merchant, $merchantPrivate);
        openssl_pkey_export($nagad, $nagadPrivate);
        $merchantPublic = openssl_pkey_get_details($merchant)['key'];
        $nagadPublic = openssl_pkey_get_details($nagad)['key'];
        // Keys as Nagad hands them out: bare base64 without the PEM lines.
        $bare = fn (string $pem) => preg_replace('/-----[^-]+-----|\s+/', '', $pem);
        $this->enable($f['branch'], 'nagad', ['merchant_id' => '683002007104225', 'merchant_number' => '01711428036', 'server_ip' => '203.0.113.10'],
            ['merchant_private_key' => $bare($merchantPrivate), 'nagad_public_key' => $bare($nagadPublic)]);
        $base = 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs';
        $sent = [];
        $tran = null;
        Http::fake([
            "$base/check-out/initialize/*" => function (HttpRequest $r) use ($merchantPublic, $nagadPrivate, $nagadPublic, &$sent) {
                // Nagad side: decrypt with its private key, check the merchant's signature, answer encrypted for the merchant.
                openssl_private_decrypt(base64_decode($r['sensitiveData']), $plain, $nagadPrivate);
                $sent['init'] = json_decode($plain, true);
                $sent['init_signature_ok'] = openssl_verify($plain, base64_decode($r['signature']), $merchantPublic, OPENSSL_ALGO_SHA256) === 1;
                $reply = json_encode(['paymentReferenceId' => 'NGREF1', 'challenge' => 'CH-FROM-NAGAD', 'acceptDateTime' => '20260315100000']);
                openssl_public_encrypt($reply, $cipher, $merchantPublic);
                openssl_sign($reply, $signature, $nagadPrivate, OPENSSL_ALGO_SHA256);

                return Http::response(['sensitiveData' => base64_encode($cipher), 'signature' => base64_encode($signature)]);
            },
            "$base/check-out/complete/NGREF1" => function (HttpRequest $r) use ($nagadPrivate, &$sent) {
                openssl_private_decrypt(base64_decode($r['sensitiveData']), $plain, $nagadPrivate);
                $sent['complete'] = json_decode($plain, true);
                $sent['callback'] = $r['merchantCallbackURL'];
                $sent['headers'] = [$r->header('X-KM-Api-Version')[0] ?? null, $r->header('X-KM-IP-V4')[0] ?? null];

                return Http::response(['status' => 'Success', 'callBackUrl' => 'https://sandbox-ssl.mynagad.com:10060/check-out/MDExNT']);
            },
            "$base/verify/payment/NGREF1" => function () use (&$tran) {
                return Http::response(['merchantId' => '683002007104225', 'orderId' => $tran, 'paymentRefId' => 'NGREF1', 'amount' => '1000', 'status' => 'Success',
                    'issuerPaymentRefNo' => '71A2B3C4', 'statusCode' => '000']);
            },
        ]);

        $this->start($f, 'nagad')->assertRedirect('https://sandbox-ssl.mynagad.com:10060/check-out/MDExNT');
        $attempt = $this->attempt($f['branch']);
        $tran = $attempt->tran_id;
        $this->assertSame('NGREF1', $attempt->gateway_ref);
        $this->assertSame($tran, $sent['init']['orderId']);
        $this->assertTrue($sent['init_signature_ok']);
        $this->assertSame(['currencyCode' => '050', 'amount' => '1000.00', 'challenge' => 'CH-FROM-NAGAD'],
            array_intersect_key($sent['complete'], array_flip(['currencyCode', 'amount', 'challenge'])));
        $this->assertSame(['v-0.2.0', '203.0.113.10'], $sent['headers']);

        $this->get($sent['callback'].'?merchant=683002007104225&order_id='.$tran.'&payment_ref_id=NGREF1&status=Success&status_code=00_0000_000');
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertSame('71A2B3C4', $attempt->fresh()->gateway_txn_id);
        $this->assertSame('nagad', $this->receipts($f['branch'])->sole()->method);
        unset($merchantPublic);
    }

    public function test_reconciliation_settles_late_payments_and_expires_abandoned_ones(): void
    {
        $f = $this->fixture();
        $this->enable($f['branch'], 'sslcommerz', ['store_id' => 'testbox'], ['store_passwd' => 'qwerty']);
        $paidTran = null;
        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay', 'sessionkey' => 'S']),
            'https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php*' => function (HttpRequest $r) use (&$paidTran) {
                $found = $r['tran_id'] === $paidTran;

                return Http::response(['APIConnect' => 'DONE', 'no_of_trans_found' => (int) $found, 'element' => $found
                    ? [['status' => 'VALIDATED', 'tran_id' => $paidTran, 'val_id' => 'V1', 'amount' => '1000.00', 'currency_type' => 'BDT', 'currency_amount' => '1000.00', 'bank_tran_id' => 'LATE1']]
                    : []]);
            },
        ]);
        $this->start($f, 'sslcommerz');
        $abandoned = $this->attempt($f['branch']);
        $this->travel(4)->minutes();
        $this->start($f, 'sslcommerz');
        $late = $this->attempt($f['branch']);
        $paidTran = $late->tran_id;

        // Neither payer came back. Ten minutes later the scheduler asks SSLCOMMERZ.
        $this->travel(10)->minutes();
        $this->inBranch($f['branch'], fn () => Artisan::call('payments:reconcile'));
        $this->assertSame('paid', $late->fresh()->status);
        $this->assertSame('initiated', $abandoned->fresh()->status);
        $this->assertCount(1, $this->receipts($f['branch']));

        $this->travel(3)->hours();
        $this->inBranch($f['branch'], fn () => Artisan::call('payments:reconcile'));
        $this->assertSame('expired', $abandoned->fresh()->status);
        $this->assertCount(1, $this->receipts($f['branch']));
    }

    public function test_access_rules_and_settings_keep_secrets_encrypted(): void
    {
        $f = $this->fixture();
        $other = $this->enrolStudent($f['branch'], $f['setup'], 'Other child');
        $this->enable($f['branch'], 'sslcommerz', ['store_id' => 'testbox'], ['store_passwd' => 'qwerty']);
        Http::fake();

        // A parent can only pay for their own children; staff cannot use the payer routes.
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$other->id}"), ['gateway' => 'sslcommerz', 'invoice_ids' => [$f['invoice']->id]])->assertNotFound();
        $this->actingOnBranch($f['admin'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$f['student']->id}"), ['gateway' => 'sslcommerz', 'invoice_ids' => [$f['invoice']->id]])->assertForbidden();
        // A disabled gateway is not accepted.
        $this->start($f, 'bkash')->assertSessionHasErrors('gateway');
        Http::assertNothingSent();

        $teacher = $this->branchUser($f['branch'], 'teacher', ['user_type' => UserType::Teacher]);
        $this->actingOnBranch($teacher, $f['branch'])->get($this->branchUrl($f['branch'], 'finance/online-payments'))->assertForbidden();
        $this->actingOnBranch($teacher, $f['branch'])->get($this->branchUrl($f['branch'], 'finance/online-payments/settings'))->assertForbidden();

        $form = ['bkash' => ['enabled' => 1, 'sandbox' => 1, 'username' => 'merchantuser', 'app_key' => 'appkey1', 'password' => 'bk-pass-123', 'app_secret' => 'bk-secret-456']];
        $this->actingOnBranch($f['admin'], $f['branch'])->put($this->branchUrl($f['branch'], 'finance/online-payments/settings'), $form)->assertSessionHasNoErrors();
        $row = SystemSetting::query()->where('key', 'bkash_secrets')->sole();
        $this->assertTrue((bool) $row->is_encrypted);
        $this->assertStringNotContainsString('bk-secret-456', json_encode($row->value));
        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], 'finance/online-payments/settings'))->assertOk()
            ->assertSee('merchantuser')->assertDontSee('bk-pass-123')->assertDontSee('bk-secret-456');

        // Saving again with empty secrets keeps them; the other gateways were switched off by the form.
        $form['bkash']['password'] = '';
        $form['bkash']['app_secret'] = '';
        $this->actingOnBranch($f['admin'], $f['branch'])->put($this->branchUrl($f['branch'], 'finance/online-payments/settings'), $form)->assertSessionHasNoErrors();
        $this->inBranch($f['branch'], function () {
            $settings = app(PaymentSettings::class);
            $this->assertSame('bk-secret-456', $settings->config('bkash')['app_secret']);
            $this->assertSame(['bkash'], $settings->enabled());
        });
    }

    public function test_mobile_app_pays_through_the_api_and_lands_on_the_public_result_page(): void
    {
        $f = $this->fixture();
        $this->enable($f['branch'], 'sslcommerz', ['store_id' => 'testbox'], ['store_passwd' => 'qwerty']);
        $tran = null;
        Http::fake([
            'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' => Http::response(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/app', 'sessionkey' => 'S']),
            'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php*' => function () use (&$tran) {
                return Http::response(['status' => 'VALID', 'tran_id' => $tran, 'amount' => '1000.00', 'currency_type' => 'BDT', 'currency_amount' => '1000.00', 'bank_tran_id' => 'APP1']);
            },
        ]);
        $host = 'http://'.$f['branch']->subdomain.'.'.config('tenancy.base_domain');
        $token = $this->postJson("$host/api/v1/auth/login", ['login' => $f['parent']->phone, 'password' => 'secret123', 'device_name' => 'app'])->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson("$host/api/v1/my/online-payments/gateways")->assertOk()->assertJsonPath('data.0.key', 'sslcommerz');
        $this->withToken($token)->postJson("$host/api/v1/my/online-payments", ['student_id' => $f['student']->id, 'gateway' => 'sslcommerz', 'invoice_ids' => [$f['invoice']->id]])
            ->assertCreated()->assertJsonPath('data.redirect_url', 'https://sandbox.sslcommerz.com/pay/app');
        $tran = $this->attempt($f['branch'])->tran_id;

        $this->post($this->returnUrl($f['branch'], 'sslcommerz', $tran), ['tran_id' => $tran, 'val_id' => 'V'])
            ->assertRedirect($this->branchUrl($f['branch'], "online-payments/done/$tran"));
        $this->get($this->branchUrl($f['branch'], "online-payments/done/$tran"))->assertOk()->assertSee('Payment received')->assertDontSee('Anika');
        $this->withToken($token)->getJson("$host/api/v1/my/online-payments/$tran")->assertOk()->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.receipt_no', $this->receipts($f['branch'])->sole()->receipt_no);

        // Someone else's child is not found.
        $other = $this->enrolStudent($f['branch'], $f['setup'], 'Other child');
        $this->withToken($token)->postJson("$host/api/v1/my/online-payments", ['student_id' => $other->id, 'gateway' => 'sslcommerz', 'invoice_ids' => [$f['invoice']->id]])->assertNotFound();
    }

    public function test_shatajpay_is_the_default_gateway_and_its_unsigned_callback_is_held_for_review(): void
    {
        $f = $this->fixture();
        $this->enable($f['branch'], 'sslcommerz', ['store_id' => 'testbox'], ['store_passwd' => 'qwerty']);
        $this->enable($f['branch'], 'shatajpay', ['username' => 'school-app', 'app_key' => 'AK1', 'gateway_id' => 'GW7'], ['password' => 'sp-pass', 'app_secret' => 'sp-secret']);
        Http::fake(['https://pay.shataj.com/api/v1/user/payment-system' => Http::response(['status' => true, 'status_code' => 200, 'message' => 'Payment initialized successfully',
            'redirect_url' => 'https://pay.shataj.com/checkout/abc'])]);

        // Listed first in the portal, so it is the preselected choice.
        $page = $this->actingOnBranch($f['parent'], $f['branch'])->get($this->branchUrl($f['branch'], "parent/children/{$f['student']->id}/fees"))->assertOk()->getContent();
        $this->assertLessThan(strpos($page, 'id="gw_sslcommerz"'), strpos($page, 'id="gw_shatajpay"'));
        $this->assertMatchesRegularExpression('/id="gw_shatajpay" value="shatajpay" checked/', $page);

        $this->start($f, 'shatajpay')->assertRedirect('https://pay.shataj.com/checkout/abc');
        $attempt = $this->attempt($f['branch']);
        $orderId = $attempt->gateway_ref;
        $this->assertTrue(\Illuminate\Support\Str::isUuid($orderId));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://pay.shataj.com/api/v1/user/payment-system' && $r->method() === 'POST'
            && $r->header('username')[0] === 'school-app' && $r->header('password')[0] === 'sp-pass' && $r->header('appkey')[0] === 'AK1'
            && $r->header('appsecret')[0] === 'sp-secret' && $r->header('gatewayid')[0] === 'GW7' && $r->header('sandbox')[0] === '0'
            && $r['amount'] == 1000 && $r['currency'] === 'BDT' && $r['orderId'] === $orderId && $r['transactionId'] === $attempt->tran_id);

        // A "Complete" callback alone never posts a receipt: it waits for the accounts office.
        $this->get($this->branchUrl($f['branch'], "gateway/payment/callback?status=Complete&orderId=$orderId"))
            ->assertRedirect($this->branchUrl($f['branch'], "online-payments/status/{$attempt->tran_id}"));
        $attempt->refresh();
        $this->assertSame('review', $attempt->status);
        $this->assertStringContainsString('ShatajPay', $attempt->failure_reason);
        $this->assertCount(0, $this->receipts($f['branch']));
        $this->actingOnBranch($f['parent'], $f['branch'])->get($this->branchUrl($f['branch'], "online-payments/status/{$attempt->tran_id}"))->assertOk()->assertSee('do not need to pay again');

        // Guessing another order id does nothing.
        $this->get($this->branchUrl($f['branch'], 'gateway/payment/callback?status=Complete&orderId='.\Illuminate\Support\Str::uuid()))->assertRedirect();
        $this->assertSame(1, $this->inBranch($f['branch'], fn () => OnlinePaymentEvent::query()->where('result', 'unknown transaction')->count()));

        $this->actingOnBranch($f['admin'], $f['branch'])->get($this->branchUrl($f['branch'], 'finance/online-payments/settings'))->assertOk()
            ->assertSee('/gateway/payment/callback')->assertDontSee('sp-secret');
        $this->actingOnBranch($f['admin'], $f['branch'])->post($this->branchUrl($f['branch'], "finance/online-payments/{$attempt->id}/review"), [
            'decision' => 'accept', 'note' => 'Seen as paid in the ShatajPay panel',
        ])->assertSessionHasNoErrors();
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertSame(1000.0, $this->receipts($f['branch'])->sole()->amount);
        $this->assertSame('paid', $f['invoice']->fresh()->status);

        // A failed payment is believed at once.
        $this->inBranch($f['branch'], fn () => app(FeeInvoiceService::class)->generateForPeriod('2026-04'));
        $april = $this->inBranch($f['branch'], fn () => FeeInvoice::query()->where('student_id', $f['student']->id)->open()->sole());
        $this->actingOnBranch($f['parent'], $f['branch'])->post($this->branchUrl($f['branch'], "online-payments/start/{$f['student']->id}"), ['gateway' => 'shatajpay', 'invoice_ids' => [$april->id]]);
        $second = $this->attempt($f['branch']);
        $this->get($this->branchUrl($f['branch'], "gateway/payment/callback?status=Failed&orderId={$second->gateway_ref}"));
        $this->assertSame('failed', $second->fresh()->status);
    }
}
