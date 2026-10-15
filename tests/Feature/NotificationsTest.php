<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Events\Attendance\ConsecutiveAbsenceDetected;
use App\Events\Finance\FeePaymentReceived;
use App\Mail\NotificationMail;
use App\Models\Attendance\AttendanceAlert;
use App\Models\Branch;
use App\Models\Communication\MessageTemplate;
use App\Models\Communication\NotificationMessage;
use App\Models\Communication\NotificationOptOut;
use App\Models\Students\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\FeePaymentService;
use App\Services\Notifications\NotificationHub;
use App\Services\Notifications\NotificationSettings;
use App\Services\Notifications\Sms\BulkSmsBdGateway;
use App\Services\Notifications\Sms\SslWirelessGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
        Http::preventStrayRequests();
    }

    protected function configure(Branch $branch, array $values): void
    {
        $this->inBranch($branch, function () use ($values) {
            $settings = app(NotificationSettings::class);
            foreach ($values as $key => $value) {
                $settings->set($key, $value, in_array($key, ['sms_secrets', 'whatsapp_secrets', 'smtp_secrets'], true));
            }
        });
    }

    protected function sslWireless(Branch $branch): void
    {
        $this->configure($branch, ['sms_enabled' => true, 'sms_driver' => 'ssl_wireless', 'sms_config' => ['sid' => 'SCHOOLNB'], 'sms_secrets' => ['api_token' => 'tok-123']]);
    }

    protected function sslOk(string $ref = 'REF1')
    {
        return Http::response(['status' => 'SUCCESS', 'status_code' => 200, 'smsinfo' => [['sms_status' => 'SUCCESS', 'reference_id' => $ref]]]);
    }

    /** Portal logins for a student and the student's first guardian (through the admin screens). */
    protected function portalUsers(Branch $branch, User $admin, Student $student): array
    {
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $guardian = $this->inBranch($branch, fn () => $student->guardians()->first());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();

        return [User::query()->where('username', strtolower($student->student_code))->sole(), User::query()->where('phone', $guardian->phone)->sole(), $guardian];
    }

    protected function messages(Branch $branch, array $where = [])
    {
        return $this->inBranch($branch, fn () => NotificationMessage::query()->where($where)->orderBy('id')->get());
    }

    public function test_provider_secrets_are_encrypted_and_never_shown_again(): void
    {
        $branch = $this->makeBranch();
        $admin = $this->branchUser($branch, 'branch-admin');
        $form = [
            'sms_enabled' => 1, 'email_enabled' => 0, 'whatsapp_enabled' => 0, 'default_locale' => 'bn', 'sms_driver' => 'ssl_wireless',
            'sms' => ['sid' => 'SCHOOLNB', 'api_token' => 'secret-token-123'], 'fee_reminder_days' => 3,
            'event_channels' => ['payment.received' => ['in_app', 'sms']],
        ];
        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, 'communication/settings'), $form)->assertSessionHasNoErrors();

        $row = SystemSetting::query()->where('key', 'sms_secrets')->sole();
        $this->assertTrue((bool) $row->is_encrypted);
        $this->assertStringNotContainsString('secret-token-123', json_encode($row->value));
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/settings'))->assertOk()
            ->assertDontSee('secret-token-123')->assertSee('SCHOOLNB');

        // Saving again with the secret left empty keeps it.
        unset($form['sms']['api_token']);
        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, 'communication/settings'), $form)->assertSessionHasNoErrors();
        $this->inBranch($branch, function () {
            $settings = app(NotificationSettings::class);
            $this->assertSame('secret-token-123', $settings->smsConfig()['api_token']);
            $this->assertSame(['in_app', 'sms'], $settings->eventChannels('payment.received'));
            $this->assertSame([], $settings->eventChannels('fee.invoice'));
            $this->assertSame(['in_app', 'sms'], $settings->enabledChannels());
        });

        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'communication/settings'))->assertForbidden();
        $accountant = $this->branchUser($branch, 'accountant');
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, 'communication/messages'))->assertForbidden();
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, 'communication/messages/create'))->assertForbidden();
    }

    public function test_bulk_sms_to_a_class_goes_through_ssl_wireless_and_respects_opt_outs(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $b = $this->enrolStudent($branch, $setup, 'Babul', $setup['sectionB']);
        $c = $this->enrolStudent($branch, $setup, 'Chandni', $setup['nextSection']);
        $this->sslWireless($branch);
        Http::fake([SslWirelessGateway::URL => $this->sslOk()]);

        $bGuardianPhone = $this->inBranch($branch, fn () => $b->guardians()->first()->phone);
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/opt-outs'), ['channel' => 'sms', 'address' => $bGuardianPhone])->assertSessionHasNoErrors();

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/messages'), [
            'title' => 'Exam routine', 'audience' => 'classes', 'class_ids' => [$setup['class']->id], 'channels' => ['sms', 'in_app'],
            'body_bn' => 'অর্ধবার্ষিক পরীক্ষার রুটিন প্রকাশিত হয়েছে।',
        ])->assertSessionHasNoErrors();

        $sms = $this->messages($branch, ['channel' => 'sms']);
        // Class Six = Anika (A) and Babul (B). Students have no mobile number, so only their two guardians get an SMS row.
        $this->assertCount(2, $sms);
        $this->assertSame(['guardian'], $sms->pluck('recipient_type')->unique()->values()->all());
        $this->assertSame(['sent', 'skipped'], $sms->pluck('status')->sort()->values()->all());
        $sent = $sms->firstWhere('status', 'sent');
        $this->assertSame('ssl_wireless', $sent->provider);
        $this->assertSame('REF1', $sent->provider_message_id);
        $this->assertGreaterThanOrEqual(1, $sent->segments);
        $this->assertStringContainsString('পরীক্ষার রুটিন', $sent->body);
        $this->assertSame('skipped', $sms->firstWhere('to', \App\Services\Notifications\PhoneNumber::normalize($bGuardianPhone))->status);
        $this->assertNull($sms->firstWhere('recipient_name', 'Chandni'));   // class Seven is not in the audience

        Http::assertSent(fn (HttpRequest $r) => $r->url() === SslWirelessGateway::URL && $r['api_token'] === 'tok-123' && $r['sid'] === 'SCHOOLNB'
            && preg_match('/^8801\d{9}$/', $r['msisdn']) && str_contains($r['sms'], 'পরীক্ষার রুটিন') && strlen($r['csms_id']) <= 20);
        Http::assertSentCount($sms->where('status', 'sent')->count());

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/messages'))->assertOk()->assertSee('Exam routine');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "communication/messages/{$sent->id}"))->assertOk()->assertSee('REF1');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/messages/usage'))->assertOk();
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/opt-outs'))->assertOk()->assertSee(\App\Services\Notifications\PhoneNumber::normalize($bGuardianPhone));
    }

    public function test_fee_payment_notifies_guardian_by_sms_and_in_app_once(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        [, $parent] = $this->portalUsers($branch, $admin, $a);
        $this->configure($branch, ['sms_enabled' => true, 'sms_driver' => 'log']);

        $payment = $this->inBranch($branch, function () use ($a, $admin) {
            app(ChartOfAccounts::class)->ensure();

            return app(FeePaymentService::class)->collect($a, 1500, 'cash', null, '2026-03-15', null, null, [], $admin);
        });

        $sms = $this->messages($branch, ['event' => 'payment.received', 'channel' => 'sms'])->sole();
        $this->assertSame('sent', $sms->status);
        $this->assertSame('log', $sms->provider);
        $this->assertStringContainsString('1,500.00', $sms->body);
        $this->assertStringContainsString($payment->receipt_no, $sms->body);
        $inApp = $this->messages($branch, ['event' => 'payment.received', 'channel' => 'in_app'])->sole();
        $this->assertSame($parent->id, $inApp->user_id);
        $this->assertSame('delivered', $inApp->status);

        // The same receipt never notifies twice.
        $this->inBranch($branch, fn () => FeePaymentReceived::dispatch($payment));
        $this->assertCount(2, $this->messages($branch, ['event' => 'payment.received']));

        // Inbox: badge count, list, open marks read.
        $this->actingOnBranch($parent, $branch)->getJson($this->branchUrl($branch, 'inbox/unread'))->assertOk()->assertJsonPath('count', 1);
        $this->actingOnBranch($parent, $branch)->get($this->branchUrl($branch, 'inbox'))->assertOk()->assertSee($inApp->subject);
        $this->actingOnBranch($parent, $branch)->get($this->branchUrl($branch, 'parent'))->assertOk()->assertSee('data-inbox-bell', false);
        $this->actingOnBranch($parent, $branch)->get($this->branchUrl($branch, "inbox/{$inApp->id}"))->assertRedirect();
        $this->assertNotNull($inApp->fresh()->read_at);
        $this->actingOnBranch($parent, $branch)->getJson($this->branchUrl($branch, 'inbox/unread'))->assertJsonPath('count', 0);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "inbox/{$inApp->id}"))->assertNotFound();
    }

    public function test_transient_failures_are_retried_and_permanent_ones_fail_at_once(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->enrolStudent($branch, $setup, 'Anika');
        $this->sslWireless($branch);
        Http::fake([SslWirelessGateway::URL => Http::sequence()->push(['error' => 'down'], 500)->push(['error' => 'down'], 503)->push(['error' => 'down'], 500)
            ->push(['status' => 'SUCCESS', 'status_code' => 200, 'smsinfo' => [['sms_status' => 'SUCCESS', 'reference_id' => 'REF9']]], 200)]);

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/messages'), [
            'title' => 'Holiday', 'audience' => 'guardians', 'channels' => ['sms'], 'body_en' => 'School is closed tomorrow.',
        ])->assertSessionHasNoErrors();
        $message = $this->messages($branch, ['channel' => 'sms'])->sole();
        $this->assertSame('queued', $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('HTTP 500', $message->error);

        $this->inBranch($branch, function () use ($message) {
            $hub = app(NotificationHub::class);
            $this->assertSame('retry', $hub->process($message->id));
            $this->assertSame('failed', $hub->process($message->id));
            $this->assertNull($hub->process($message->id), 'A failed message is not processed again by a stray job');
        });
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertSame(3, $message->fresh()->attempts);

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "communication/messages/{$message->id}/retry"))->assertSessionHasNoErrors();
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('REF9', $message->fresh()->provider_message_id);

        // BulkSMSBD 1032 (IP not whitelisted) is a configuration problem: no retries.
        $this->configure($branch, ['sms_driver' => 'bulksmsbd', 'sms_config' => ['sender_id' => '8809617'], 'sms_secrets' => ['api_key' => 'k']]);
        Http::fake([BulkSmsBdGateway::URL => Http::response(['response_code' => 1032, 'error_message' => 'IP not whitelisted'])]);
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/messages'), [
            'title' => 'Second', 'audience' => 'guardians', 'channels' => ['sms'], 'body_en' => 'Hello again.',
        ])->assertSessionHasNoErrors();
        $second = $this->messages($branch, ['channel' => 'sms'])->last();
        $this->assertSame('failed', $second->status);
        $this->assertSame(1, $second->attempts);
        $this->assertStringContainsString('1032', $second->error);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === BulkSmsBdGateway::URL && $r['senderid'] === '8809617' && $r['type'] === 'text');
    }

    public function test_scheduled_bulk_message_waits_for_its_time_and_can_be_cancelled(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->enrolStudent($branch, $setup, 'Anika');
        $this->enrolStudent($branch, $setup, 'Babul');
        $this->sslWireless($branch);
        Http::fake([SslWirelessGateway::URL => $this->sslOk()]);

        foreach (['Tomorrow 1', 'Tomorrow 2'] as $title) {
            $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/messages'), [
                'title' => $title, 'audience' => 'guardians', 'channels' => ['sms'], 'body_en' => $title, 'scheduled_at' => '2026-03-16 08:00',
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame(['scheduled'], $this->messages($branch)->pluck('status')->unique()->values()->all());
        Http::assertNothingSent();

        $second = $this->inBranch($branch, fn () => \App\Models\Communication\NotificationBatch::query()->where('title', 'Tomorrow 2')->sole());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "communication/messages/batches/{$second->id}/cancel"))->assertSessionHasNoErrors();

        $this->inBranch($branch, fn () => Artisan::call('notifications:dispatch'));
        Http::assertNothingSent();

        $this->travelTo(now()->setDate(2026, 3, 16)->setTime(8, 1));
        $this->inBranch($branch, fn () => Artisan::call('notifications:dispatch'));
        $this->assertSame(['sent'], $this->messages($branch, ['notification_batch_id' => $second->id - 1])->pluck('status')->unique()->values()->all());
        $this->assertSame(['cancelled'], $this->messages($branch, ['notification_batch_id' => $second->id])->pluck('status')->unique()->values()->all());
        Http::assertSentCount(2);
    }

    public function test_whatsapp_template_send_and_signed_status_webhook(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $this->configure($branch, [
            'whatsapp_enabled' => true, 'whatsapp_config' => ['phone_number_id' => '1234567890', 'api_version' => 'v23.0'],
            'whatsapp_secrets' => ['access_token' => 'EAAG-token', 'app_secret' => 'app-secret', 'verify_token' => 'verify-me'],
            'event_channels' => ['payment.received' => ['whatsapp']],
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.ABC']]])]);

        // Without an approved template name the message is recorded as skipped.
        $pay = fn () => $this->inBranch($branch, function () use ($a, $admin) {
            app(ChartOfAccounts::class)->ensure();

            return app(FeePaymentService::class)->collect($a, 800, 'cash', null, '2026-03-15', null, null, [], $admin);
        });
        $pay();
        $this->assertSame('skipped', $this->messages($branch, ['channel' => 'whatsapp'])->sole()->status);

        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, 'communication/templates/payment.received'), [
            'bn' => ['whatsapp_template' => 'fee_receipt', 'whatsapp_language' => 'bn'], 'en' => ['whatsapp_template' => 'fee_receipt', 'whatsapp_language' => 'en'],
        ])->assertSessionHasNoErrors();
        $payment = $pay();
        $message = $this->messages($branch, ['channel' => 'whatsapp'])->last();
        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.ABC', $message->provider_message_id);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://graph.facebook.com/v23.0/1234567890/messages'
            && $r->hasHeader('Authorization', 'Bearer EAAG-token') && $r['template']['name'] === 'fee_receipt' && $r['template']['language']['code'] === 'bn'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'Anika' && $r['template']['components'][0]['parameters'][1]['text'] === '800.00'
            && $r['template']['components'][0]['parameters'][2]['text'] === $payment->receipt_no);

        // Webhook subscription check.
        $this->get($this->branchUrl($branch, 'webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=4242'))->assertOk()->assertSee('4242');
        $this->get($this->branchUrl($branch, 'webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=4242'))->assertForbidden();

        $post = function (string $status, ?string $secret = 'app-secret') use ($branch) {
            $json = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => [
                'statuses' => [['id' => 'wamid.ABC', 'status' => $status, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '8801700000000']],
            ]]]]]]);

            return $this->call('POST', $this->branchUrl($branch, 'webhooks/whatsapp'), [], [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, (string) $secret)], $json);
        };
        $post('delivered', 'forged')->assertForbidden();
        $this->assertSame('sent', $message->fresh()->status);
        $post('delivered')->assertOk()->assertJsonPath('updated', 1);
        $this->assertSame('delivered', $message->fresh()->status);
        $post('read')->assertOk();
        $this->assertNotNull($message->fresh()->read_at);
        $post('sent')->assertOk()->assertJsonPath('updated', 0);   // never moves backwards
        $this->assertSame('delivered', $message->fresh()->status);
    }

    public function test_branch_template_override_and_unknown_variable_warning(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $this->configure($branch, ['sms_enabled' => true, 'sms_driver' => 'log']);

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/templates'))->assertOk()->assertSee('payment.received');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/templates/payment.received'))->assertOk();
        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, 'communication/templates/payment.received'), [
            'bn' => ['sms' => 'ধন্যবাদ! {student_name}-এর {amount} টাকা পেয়েছি। {foo}'],
        ])->assertSessionHasNoErrors()->assertSessionHas('warning');

        $this->inBranch($branch, function () use ($a, $admin) {
            app(ChartOfAccounts::class)->ensure();
            app(FeePaymentService::class)->collect($a, 700, 'cash', null, '2026-03-15', null, null, [], $admin);
        });
        $sms = $this->messages($branch, ['channel' => 'sms'])->sole();
        $this->assertSame('ধন্যবাদ! Anika-এর 700.00 টাকা পেয়েছি।', $sms->body);

        $this->actingOnBranch($admin, $branch)->delete($this->branchUrl($branch, 'communication/templates/payment.received'))->assertSessionHasNoErrors();
        $this->assertSame(0, $this->inBranch($branch, fn () => MessageTemplate::query()->count()));
    }

    public function test_absence_alert_and_email_channel(): void
    {
        Mail::fake();
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $this->configure($branch, ['sms_enabled' => true, 'sms_driver' => 'log', 'email_enabled' => true, 'event_channels' => ['attendance.absent' => ['sms', 'email']]]);
        $this->inBranch($branch, fn () => $a->guardians()->first()->update(['email' => 'Guardian.Anika@Example.com']));

        $alert = $this->inBranch($branch, function () use ($a, $setup) {
            $alert = AttendanceAlert::query()->create(['student_id' => $a->id, 'section_id' => $setup['sectionA']->id, 'streak_days' => 3,
                'first_absent_on' => '2026-03-11', 'last_absent_on' => '2026-03-14', 'status' => 'open']);
            ConsecutiveAbsenceDetected::dispatch($alert);

            return $alert;
        });

        $this->assertNotNull($alert->fresh()->notified_at);
        $sms = $this->messages($branch, ['event' => 'attendance.absent', 'channel' => 'sms'])->sole();
        $this->assertStringContainsString('11/03/2026', $sms->body);
        $this->assertStringContainsString('3', $sms->body);
        $email = $this->messages($branch, ['event' => 'attendance.absent', 'channel' => 'email'])->sole();
        $this->assertSame('guardian.anika@example.com', $email->to);
        $this->assertSame('sent', $email->status);
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $m) => $m->hasTo('guardian.anika@example.com') && str_contains($m->messageText, 'Anika'));
    }

    public function test_guardian_can_opt_out_in_the_portal(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        [$student, $parent, $guardian] = $this->portalUsers($branch, $admin, $a);

        $this->actingOnBranch($parent, $branch)->get($this->branchUrl($branch, 'message-preferences'))->assertOk();
        $this->actingOnBranch($parent, $branch)->put($this->branchUrl($branch, 'message-preferences'), ['sms' => 0, 'whatsapp' => 1])->assertSessionHasNoErrors();
        $this->inBranch($branch, function () use ($guardian) {
            $phone = \App\Services\Notifications\PhoneNumber::normalize($guardian->phone);
            $this->assertTrue(NotificationOptOut::query()->where(['channel' => 'sms', 'address' => $phone])->exists());
            $this->assertFalse(NotificationOptOut::query()->where(['channel' => 'whatsapp', 'address' => $phone])->exists());
        });
        $this->actingOnBranch($parent, $branch)->put($this->branchUrl($branch, 'message-preferences'), ['sms' => 1, 'whatsapp' => 1, 'email' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->inBranch($branch, fn () => NotificationOptOut::query()->count()));

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'message-preferences'))->assertForbidden();
    }

    public function test_shataj_sms_sends_through_the_chosen_device_and_picks_up_the_delivery_status(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->enrolStudent($branch, $setup, 'Anika');
        $form = ['sms_enabled' => 1, 'email_enabled' => 0, 'whatsapp_enabled' => 0, 'default_locale' => 'en', 'sms_driver' => 'shataj_sms',
            'sms' => ['api_key' => 'shataj-key-1', 'server' => 'https://sms.shataj.com', 'device' => '2|0'], 'fee_reminder_days' => 3];
        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, 'communication/settings'), $form)->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/settings'))->assertOk()->assertSee('Shataj SMS')->assertDontSee('shataj-key-1');
        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, 'communication/settings'), ['sms' => ['device' => 'phone one']] + $form)->assertSessionHasErrors('sms.device');

        Http::fake([
            'https://sms.shataj.com/services/send.php' => Http::response(['success' => true, 'data' => ['messages' => [['ID' => '55', 'status' => 'Pending', 'number' => '+8801711000000']]]]),
            'https://sms.shataj.com/services/read-messages.php' => Http::sequence()
                ->push(['success' => true, 'data' => ['messages' => [['ID' => '55', 'status' => 'Queued']]]])
                ->push(['success' => true, 'data' => ['messages' => [['ID' => '55', 'status' => 'Delivered']]]]),
        ]);
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/messages'), [
            'title' => 'Holiday', 'audience' => 'guardians', 'channels' => ['sms'], 'body_en' => 'School is closed tomorrow.',
        ])->assertSessionHasNoErrors();
        $message = $this->messages($branch, ['channel' => 'sms'])->sole();
        $this->assertSame('sent', $message->status);
        $this->assertSame('shataj_sms', $message->provider);
        $this->assertSame('55', $message->provider_message_id);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://sms.shataj.com/services/send.php' && $r['key'] === 'shataj-key-1' && $r['devices'] === '2|0'
            && $r['type'] === 'sms' && $r['number'] === '+'.$message->to && str_contains($r['message'], 'School is closed tomorrow.'));

        // The phone sends it a little later: the minute scheduler asks for the final state.
        $this->travel(2)->minutes();
        $this->inBranch($branch, fn () => Artisan::call('notifications:dispatch'));
        $this->assertSame('sent', $message->fresh()->status);
        $this->travel(1)->minutes();
        $this->inBranch($branch, fn () => Artisan::call('notifications:dispatch'));
        $this->assertSame('delivered', $message->fresh()->status);
        $this->assertNotNull($message->fresh()->delivered_at);
    }

    public function test_shataj_sms_errors_are_reported(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->enrolStudent($branch, $setup, 'Anika');
        $this->configure($branch, ['sms_enabled' => true, 'sms_driver' => 'shataj_sms', 'sms_secrets' => ['api_key' => 'bad-key']]);
        Http::fake(['https://sms.shataj.com/services/send.php' => Http::response(['success' => false, 'data' => null, 'error' => ['code' => 401, 'message' => 'Invalid API key.']])]);

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/messages'), [
            'title' => 'Holiday', 'audience' => 'guardians', 'channels' => ['sms'], 'body_en' => 'School is closed tomorrow.',
        ])->assertSessionHasNoErrors();
        $message = $this->messages($branch, ['channel' => 'sms'])->sole();
        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('Invalid API key.', $message->error);
    }
}
