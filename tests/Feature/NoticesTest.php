<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Communication\Notice;
use App\Models\Communication\NoticeRead;
use App\Models\Communication\NotificationMessage;
use App\Models\Communication\NotificationOptOut;
use App\Models\Students\Student;
use App\Models\User;
use App\Services\Notifications\NotificationSettings;
use App\Services\Notifications\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoticesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
    }

    protected function portalUsers(Branch $branch, User $admin, Student $student): array
    {
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $guardian = $this->inBranch($branch, fn () => $student->guardians()->first());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();

        return [User::query()->where('username', strtolower($student->student_code))->sole(), User::query()->where('phone', $guardian->phone)->sole()];
    }

    protected function notice(Branch $branch, array $where = []): Notice
    {
        return $this->inBranch($branch, fn () => Notice::query()->where($where)->latest('id')->firstOrFail());
    }

    public function test_class_notice_reaches_only_its_audience_with_attachment_and_read_receipts(): void
    {
        Storage::fake('local');
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $c = $this->enrolStudent($branch, $setup, 'Chandni', $setup['nextSection']);
        [$studentA, $parentA] = $this->portalUsers($branch, $admin, $a);
        [$studentC] = $this->portalUsers($branch, $admin, $c);
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/notices'), [
            'title' => 'Class Six science fair', 'body' => "The science fair is on Thursday.\nBring your projects.", 'category' => 'event',
            'audience' => 'classes', 'class_ids' => [$setup['class']->id], 'publish_now' => 1,
            'attachment' => UploadedFile::fake()->create('fair.pdf', 40, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $notice = $this->notice($branch);
        $this->assertSame('published', $notice->status);
        $this->assertNotNull($notice->distributed_at);

        // In-app notification to the audience's portal users only.
        $inApp = $this->inBranch($branch, fn () => NotificationMessage::query()->where('event', 'notice.published')->pluck('user_id')->sort()->values()->all());
        $this->assertSame(collect([$studentA->id, $parentA->id])->sort()->values()->all(), $inApp);

        foreach ([$studentA, $parentA] as $user) {
            $this->actingOnBranch($user, $branch)->get($this->branchUrl($branch, 'notices'))->assertOk()->assertSee('Class Six science fair');
            $this->actingOnBranch($user, $branch)->get($this->branchUrl($branch, "notices/{$notice->id}"))->assertOk()->assertSee('Bring your projects.');
            $this->actingOnBranch($user, $branch)->get($this->branchUrl($branch, "notices/{$notice->id}/attachment"))->assertOk();
        }
        foreach ([$studentC, $teacher] as $user) {
            $this->actingOnBranch($user, $branch)->get($this->branchUrl($branch, 'notices'))->assertOk()->assertDontSee('Class Six science fair');
            $this->actingOnBranch($user, $branch)->get($this->branchUrl($branch, "notices/{$notice->id}"))->assertNotFound();
            $this->actingOnBranch($user, $branch)->get($this->branchUrl($branch, "notices/{$notice->id}/attachment"))->assertNotFound();
        }

        $this->assertSame(2, $this->inBranch($branch, fn () => NoticeRead::query()->where('notice_id', $notice->id)->count()));
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "communication/notices/{$notice->id}"))->assertOk()->assertSee('2 <span', false);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/notices'))->assertOk()->assertSee('Class Six science fair');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "communication/notices/{$notice->id}/edit"))->assertOk();
    }

    public function test_scheduled_notice_goes_live_on_time_and_expired_notices_leave_the_board(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        [$student] = $this->portalUsers($branch, $admin, $a);

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/notices'), [
            'title' => 'Eid holidays', 'body' => 'School stays closed from 20 to 30 March.', 'category' => 'holiday', 'audience' => 'all',
            'publish_at' => '2026-03-15 12:00', 'expires_at' => '2026-03-31 23:59', 'publish_now' => 1,
        ])->assertSessionHasNoErrors();
        $notice = $this->notice($branch);
        $this->assertSame('scheduled', $notice->status);
        $this->actingOnBranch($student, $branch)->get($this->branchUrl($branch, 'notices'))->assertDontSee('Eid holidays');

        $this->travelTo(now()->setTime(12, 1));
        $this->inBranch($branch, fn () => Artisan::call('notifications:dispatch'));
        $this->assertSame('published', $notice->fresh()->status);
        $this->assertNotNull($notice->fresh()->distributed_at);
        $this->actingOnBranch($student, $branch)->get($this->branchUrl($branch, 'notices'))->assertSee('Eid holidays');
        $this->assertSame(1, $this->inBranch($branch, fn () => NotificationMessage::query()->where(['event' => 'notice.published', 'user_id' => $student->id])->count()));

        // Running the scheduler again distributes nothing twice.
        $this->inBranch($branch, fn () => Artisan::call('notifications:dispatch'));
        $this->assertSame(1, $this->inBranch($branch, fn () => NotificationMessage::query()->where(['event' => 'notice.published', 'user_id' => $student->id])->count()));

        $this->travelTo(now()->setDate(2026, 4, 2));
        $this->actingOnBranch($student, $branch)->get($this->branchUrl($branch, 'notices'))->assertDontSee('Eid holidays');
        $this->actingOnBranch($student, $branch)->get($this->branchUrl($branch, "notices/{$notice->id}"))->assertNotFound();
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'communication/notices?status=expired'))->assertOk()->assertSee('Eid holidays');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "communication/notices/{$notice->id}/archive"))->assertSessionHasNoErrors();
        $this->assertSame('archived', $notice->fresh()->status);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "communication/notices/{$notice->id}/edit"))->assertNotFound();
    }

    public function test_only_publishers_publish_and_drafts_stay_hidden(): void
    {
        $branch = $this->makeBranch();
        $coordinator = $this->branchUser($branch, 'academic-coordinator');   // notices.view + notices.create, no publish
        $this->actingOnBranch($coordinator, $branch)->post($this->branchUrl($branch, 'communication/notices'), [
            'title' => 'Draft routine', 'body' => 'Routine for next week.', 'category' => 'academic', 'audience' => 'staff', 'publish_now' => 1,
            'channels' => ['sms'],
        ])->assertSessionHasNoErrors();
        $notice = $this->notice($branch);
        $this->assertSame('draft', $notice->status);
        $this->assertSame([], $notice->channels, 'Only users who may send messages can choose SMS');

        $this->actingOnBranch($coordinator, $branch)->post($this->branchUrl($branch, "communication/notices/{$notice->id}/publish"))->assertForbidden();
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'notices'))->assertDontSee('Draft routine');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "communication/notices/{$notice->id}/publish"))->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'notices'))->assertSee('Draft routine');

        // Validation: a class notice needs classes; drafts only are deletable.
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/notices'), [
            'title' => 'X', 'body' => 'Y', 'category' => 'general', 'audience' => 'classes',
        ])->assertSessionHasErrors('class_ids');
        $this->actingOnBranch($admin, $branch)->delete($this->branchUrl($branch, "communication/notices/{$notice->id}"))->assertForbidden();

        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->actingOnBranch($teacher, $branch)->get($this->branchUrl($branch, 'communication/notices'))->assertForbidden();
    }

    public function test_emergency_notice_sms_reaches_guardians_who_opted_out(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $phone = $this->inBranch($branch, function () use ($a) {
            $settings = app(NotificationSettings::class);
            $settings->set('sms_enabled', true);
            $settings->set('sms_driver', 'log');
            $phone = PhoneNumber::normalize($a->guardians()->first()->phone);
            NotificationOptOut::query()->create(['channel' => 'sms', 'address' => $phone]);

            return $phone;
        });

        foreach (['Sports day moved' => 0, 'School closed today — flood warning' => 1] as $title => $emergency) {
            $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/notices'), [
                'title' => $title, 'body' => $title, 'category' => $emergency ? 'emergency' : 'event', 'audience' => 'guardians',
                'channels' => ['sms'], 'is_emergency' => $emergency, 'publish_now' => 1,
            ])->assertSessionHasNoErrors();
        }

        $statuses = $this->inBranch($branch, fn () => NotificationMessage::query()->where(['channel' => 'sms', 'to' => $phone])->orderBy('id')->pluck('status')->all());
        $this->assertSame(['skipped', 'sent'], $statuses);
        $this->assertTrue($this->notice($branch, ['category' => 'emergency'])->is_emergency);
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'notices'))->assertOk();
    }

    public function test_institution_notice_is_copied_to_the_chosen_branches_only(): void
    {
        $admin = User::factory()->role('institution-admin')->create();
        $north = $this->makeBranch($admin->institution, ['name' => 'North Campus']);
        $south = $this->makeBranch($admin->institution, ['name' => 'South Campus']);
        $foreign = $this->makeBranch(null, ['name' => 'Foreign Campus']);

        $this->actingCentral($admin)->get($this->centralUrl('super-admin/notices/create'))->assertOk()->assertSee('North Campus')->assertDontSee('Foreign Campus');
        $this->actingCentral($admin)->post($this->centralUrl('super-admin/notices'), [
            'title' => 'Independence Day', 'body' => 'All campuses celebrate on 26 March.', 'category' => 'holiday', 'audience' => 'all',
            'branch_ids' => [$foreign->id],
        ])->assertSessionHasErrors('branch_ids.0');

        $this->actingCentral($admin)->post($this->centralUrl('super-admin/notices'), [
            'title' => 'Independence Day', 'body' => 'All campuses celebrate on 26 March.', 'category' => 'holiday', 'audience' => 'all',
            'branch_ids' => [$north->id, $south->id], 'publish_now' => 1,
        ])->assertSessionHasNoErrors();

        $n = $this->notice($north);
        $s = $this->notice($south);
        $this->assertSame('published', $n->status);
        $this->assertSame($n->group_uuid, $s->group_uuid);
        $this->assertNotNull($n->group_uuid);
        $this->assertSame(0, $this->inBranch($foreign, fn () => Notice::query()->count()));
        $this->actingCentral($admin)->get($this->centralUrl('super-admin/notices'))->assertOk()->assertSee('Independence Day');
    }

    public function test_api_lists_only_notices_of_the_users_audience(): void
    {
        $branch = $this->makeBranch(attributes: ['subdomain' => 'branch1']);
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin', ['email' => 'admin@test.bd']);
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        [$student] = $this->portalUsers($branch, $admin, $a);
        foreach (['For guardians' => 'guardians', 'For students' => 'students'] as $title => $audience) {
            $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'communication/notices'), [
                'title' => $title, 'body' => $title.' body', 'category' => 'general', 'audience' => $audience, 'publish_now' => 1,
            ])->assertSessionHasNoErrors();
        }

        // Same student on the web board first — separates an audience problem from an API problem.
        $this->actingOnBranch($student, $branch)->get($this->branchUrl($branch, 'notices'))->assertOk()->assertSee('For students')->assertDontSee('For guardians');

        $token = $this->postJson('http://branch1.localhost/api/v1/auth/login', ['login' => $student->username, 'password' => 'secret123', 'device_name' => 'phpunit'])
            ->assertOk()->json('data.token');
        $list = $this->withToken($token)->getJson('http://branch1.localhost/api/v1/notices')->assertOk()->json('data');
        $this->assertSame(['For students'], array_column($list, 'title'));
        $guardianNotice = $this->notice($branch, ['audience' => 'guardians']);
        $this->withToken($token)->getJson("http://branch1.localhost/api/v1/notices/{$guardianNotice->id}")->assertNotFound();
        $this->withToken($token)->getJson('http://branch1.localhost/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data');
    }
}
