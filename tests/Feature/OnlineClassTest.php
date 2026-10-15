<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Communication\NotificationMessage;
use App\Models\Learning\OnlineClass;
use App\Models\Learning\OnlineClassIntegration;
use App\Models\Students\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Online / offline classes with the Zoom and Google Meet adapters. Every provider call is faked: no live account was used. */
class OnlineClassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(10, 0));
        Http::preventStrayRequests();
    }

    protected function world(): array
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $teacher = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $other = $this->branchUser($branch, 'teacher', ['user_type' => UserType::Teacher]);
        $this->inBranch($branch, fn () => $setup['sectionA']->forceFill(['class_teacher_id' => $teacher->id])->save());
        $this->inBranch($branch, fn () => $setup['nextSection']->forceFill(['class_teacher_id' => $other->id])->save());
        $subject = $this->inBranch($branch, function () use ($setup) {
            $s = \App\Models\Academic\Subject::query()->create(['name' => 'Mathematics', 'code' => 'MATH', 'type' => 'theory', 'is_active' => true]);
            \App\Models\Academic\ClassSubject::query()->create(['school_class_id' => $setup['class']->id, 'subject_id' => $s->id, 'category' => 'compulsory', 'full_marks' => 100, 'pass_marks' => 33]);

            return $s;
        });
        $a = $this->enrolStudent($branch, $setup, 'Anika');
        $c = $this->enrolStudent($branch, $setup, 'Chandni', $setup['nextSection']);
        [$studentA, $parentA] = $this->portalUsers($branch, $admin, $a);
        [$studentC] = $this->portalUsers($branch, $admin, $c);

        return compact('branch', 'setup', 'admin', 'teacher', 'other', 'subject', 'a', 'c', 'studentA', 'parentA', 'studentC');
    }

    protected function portalUsers(Branch $branch, User $admin, Student $student): array
    {
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $guardian = $this->inBranch($branch, fn () => $student->guardians()->first());
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();

        return [User::query()->where('username', strtolower($student->student_code))->sole(), User::query()->where('phone', $guardian->phone)->sole()];
    }

    protected function payload(array $w, array $overrides = []): array
    {
        return $overrides + [
            'mode' => 'online', 'provider' => 'manual', 'title' => 'Algebra revision', 'join_url' => 'https://meet.example.com/abc', 'passcode' => '1234',
            'school_class_id' => $w['setup']['class']->id, 'section_id' => $w['setup']['sectionA']->id, 'subject_id' => $w['subject']->id,
            'starts_at' => '2026-03-16T10:00', 'ends_at' => '2026-03-16T10:45',
        ];
    }

    protected function schedule(array $w, array $overrides = [], ?User $as = null): OnlineClass
    {
        $this->actingOnBranch($as ?? $w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/online-classes'), $this->payload($w, $overrides))->assertSessionHasNoErrors();

        return $this->inBranch($w['branch'], fn () => OnlineClass::query()->latest('id')->firstOrFail());
    }

    protected function configureZoom(array $w): void
    {
        $this->actingOnBranch($w['admin'], $w['branch'])->put($this->branchUrl($w['branch'], 'learning/integrations/zoom'), [
            'account_id' => 'acct_1', 'client_id' => 'zoom_client', 'client_secret' => 'ZOOM-TOP-SECRET', 'host_user' => 'me', 'is_enabled' => 1,
        ])->assertSessionHasNoErrors();
    }

    protected function configureGoogle(array $w): void
    {
        $this->actingOnBranch($w['admin'], $w['branch'])->put($this->branchUrl($w['branch'], 'learning/integrations/google_meet'), [
            'client_id' => 'google_client', 'client_secret' => 'GOOGLE-TOP-SECRET', 'is_enabled' => 1,
        ])->assertSessionHasNoErrors();
    }

    protected function fakeZoom(): void
    {
        Http::fake($this->zoomHandler());
    }

    protected function zoomHandler(): \Closure
    {
        return function (HttpRequest $request) {
            $url = $request->url();
            if (str_starts_with($url, 'https://zoom.us/oauth/token')) {
                return Http::response(['access_token' => 'zoom-access', 'expires_in' => 3600]);
            }
            if ($request->method() === 'POST' && $url === 'https://api.zoom.us/v2/users/me/meetings') {
                return Http::response(['id' => 987654321, 'join_url' => 'https://zoom.us/j/987654321?pwd=xyz', 'start_url' => 'https://zoom.us/s/987654321?zak=host', 'password' => 'zp4ss'], 201);
            }
            if ($url === 'https://api.zoom.us/v2/meetings/987654321' && in_array($request->method(), ['PATCH', 'DELETE'], true)) {
                return Http::response('', 204);
            }
            if ($request->method() === 'GET' && $url === 'https://api.zoom.us/v2/users/me') {
                return Http::response(['id' => 'u1', 'email' => 'school@example.com']);
            }
            if ($url === 'https://api.zoom.us/v2/meetings/987654321/recordings') {
                return Http::response(['share_url' => 'https://zoom.us/rec/share/abc']);
            }

            return Http::response(['message' => 'unexpected '.$request->method().' '.$url], 500);
        };
    }

    protected function idToken(string $email): string
    {
        return 'x.'.rtrim(strtr(base64_encode(json_encode(['email' => $email])), '+/', '-_'), '=').'.y';
    }

    // ------------------------------------------------------------------------------------------------------------

    public function test_teacher_schedules_a_manual_link_class_and_students_and_guardians_are_notified(): void
    {
        $w = $this->world();
        $class = $this->schedule($w);

        $this->assertSame('scheduled', $class->status);
        $this->assertSame('manual', $class->provider);
        $this->assertSame('https://meet.example.com/abc', $class->join_url);
        $this->assertSame($w['teacher']->id, $class->teacher_id);
        $users = $this->inBranch($w['branch'], fn () => NotificationMessage::query()->where('event', 'online_class.scheduled')->where('channel', 'in_app')->pluck('user_id')->sort()->values()->all());
        $this->assertSame(collect([$w['studentA']->id, $w['parentA']->id])->sort()->values()->all(), $users);

        // validation: own sections only, a section is required, link required, end after start, no provider that is not connected
        $url = $this->branchUrl($w['branch'], 'teacher/learning/online-classes');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, $this->payload($w, ['school_class_id' => $w['setup']['next']->id, 'section_id' => $w['setup']['nextSection']->id]))->assertSessionHasErrors('section_id');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, $this->payload($w, ['section_id' => null]))->assertSessionHasErrors('section_id');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, $this->payload($w, ['join_url' => null]))->assertSessionHasErrors('join_url');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, $this->payload($w, ['ends_at' => '2026-03-16T09:00']))->assertSessionHasErrors('ends_at');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($url, $this->payload($w, ['provider' => 'zoom']))->assertSessionHasErrors('provider');
        $this->assertSame(1, $this->inBranch($w['branch'], fn () => OnlineClass::query()->count()));

        // a teacher of another section does not see it; the back office does
        $this->actingOnBranch($w['other'], $w['branch'])->get($this->branchUrl($w['branch'], 'teacher/learning/online-classes'))->assertOk()->assertDontSee('Algebra revision');
        $this->actingOnBranch($w['other'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}"))->assertNotFound();
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], 'teacher/learning/online-classes'))->assertOk()->assertSee('Algebra revision');
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], 'learning/online-classes'))->assertOk()->assertSee('Algebra revision');
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], "learning/online-classes/{$class->id}"))->assertOk()->assertSee('Anika');
    }

    public function test_offline_class_has_a_room_and_no_link(): void
    {
        $w = $this->world();
        $class = $this->schedule($w, ['mode' => 'offline', 'provider' => null, 'join_url' => null, 'room' => 'Room 204', 'title' => 'Science lab']);

        $this->assertSame('offline', $class->mode);
        $this->assertNull($class->provider);
        $this->assertSame('Room 204', $class->room);
        $this->assertNull($class->join_url);
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/online-classes'))->assertOk()->assertSee('Science lab')->assertSee('Room 204');
    }

    public function test_zoom_credentials_are_encrypted_and_never_shown_and_a_class_gets_a_real_meeting(): void
    {
        $w = $this->world();
        $this->fakeZoom();
        $this->configureZoom($w);

        // secret is encrypted at rest and never rendered back
        $raw = DB::table('online_class_integrations')->where('provider', 'zoom')->first();
        $this->assertStringNotContainsString('ZOOM-TOP-SECRET', (string) $raw->config);
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], 'learning/integrations'))->assertOk()->assertDontSee('ZOOM-TOP-SECRET')->assertSee('zoom_client');
        // saving again with a blank secret keeps the old one
        $this->actingOnBranch($w['admin'], $w['branch'])->put($this->branchUrl($w['branch'], 'learning/integrations/zoom'), ['account_id' => 'acct_1', 'client_id' => 'zoom_client', 'client_secret' => '', 'is_enabled' => 1])->assertSessionHasNoErrors();
        $this->assertSame('ZOOM-TOP-SECRET', $this->inBranch($w['branch'], fn () => OnlineClassIntegration::query()->where('provider', 'zoom')->sole()->setting('client_secret')));
        // never in the audit trail
        $this->assertSame(0, DB::table('audit_logs')->where('new_values', 'like', '%ZOOM-TOP-SECRET%')->count());

        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->branchUrl($w['branch'], 'learning/integrations/zoom/test'))->assertSessionHas('success');

        $class = $this->schedule($w, ['provider' => 'zoom', 'join_url' => null, 'title' => 'Zoom geometry']);
        $this->assertSame('synced', $class->sync_status);
        $this->assertSame('987654321', $class->external_id);
        $this->assertSame('https://zoom.us/j/987654321?pwd=xyz', $class->join_url);
        $this->assertSame('zp4ss', $class->passcode);
        $this->assertSame('https://zoom.us/s/987654321?zak=host', $class->host_url);

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && $r->url() === 'https://api.zoom.us/v2/users/me/meetings'
            && $r->hasHeader('Authorization', 'Bearer zoom-access') && $r['topic'] === 'Zoom geometry' && $r['duration'] === 45 && $r['start_time'] === '2026-03-16T04:00:00Z' && $r['type'] === 2);
        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->url(), 'https://zoom.us/oauth/token') && str_contains($r->body(), 'grant_type=account_credentials') && str_contains($r->body(), 'account_id=acct_1'));

        // the host link stays with staff: students never see it
        $this->travelTo(now()->setDate(2026, 3, 16)->setTime(9, 50));
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/online-classes'))->assertOk()->assertSee('https://zoom.us/j/987654321?pwd=xyz')->assertDontSee('zak=host');
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}"))->assertOk()->assertSee('zak=host');

        // moving the class updates the Zoom meeting and tells the families
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(11, 0));
        $this->actingOnBranch($w['teacher'], $w['branch'])->put($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}"), $this->payload($w, [
            'provider' => 'zoom', 'join_url' => null, 'title' => 'Zoom geometry', 'starts_at' => '2026-03-16T11:00', 'ends_at' => '2026-03-16T12:00',
        ]))->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && $r->url() === 'https://api.zoom.us/v2/meetings/987654321' && $r['duration'] === 60);
        $this->assertSame(2, $this->inBranch($w['branch'], fn () => NotificationMessage::query()->where('event', 'online_class.rescheduled')->where('channel', 'in_app')->count()));

        // cancelling deletes the meeting and notifies
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/cancel"), ['reason' => 'Teacher is ill'])->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && $r->url() === 'https://api.zoom.us/v2/meetings/987654321');
        $class->refresh();
        $this->assertSame('cancelled', $class->status);
        $this->assertSame(2, $this->inBranch($w['branch'], fn () => NotificationMessage::query()->where('event', 'online_class.cancelled')->where('channel', 'in_app')->count()));
        // a cancelled class can no longer be edited or cancelled again
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/edit"))->assertForbidden();
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/cancel"))->assertSessionHasErrors('reason');
    }

    public function test_a_zoom_failure_keeps_the_class_and_can_be_retried(): void
    {
        $w = $this->world();
        $this->configureZoom($w);
        $down = true;
        $zoom = $this->zoomHandler();
        Http::fake(function (HttpRequest $request) use (&$down, $zoom) {
            return $down ? Http::response(['message' => 'Invalid access token.', 'code' => 124], 401) : $zoom($request);
        });

        $class = $this->schedule($w, ['provider' => 'zoom', 'join_url' => null]);
        $this->assertSame('failed', $class->sync_status);
        $this->assertNull($class->external_id);
        $this->assertStringContainsString('Zoom rejected the credentials', (string) $class->sync_error);
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}"))->assertOk()->assertSee('could not be created')->assertSee('Try again');

        $down = false;
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/sync"))->assertSessionHas('success');
        $class->refresh();
        $this->assertSame('synced', $class->sync_status);
        $this->assertSame('https://zoom.us/j/987654321?pwd=xyz', $class->join_url);
    }

    public function test_google_meet_connect_flow_creates_calendar_events_with_meet_links(): void
    {
        $w = $this->world();
        $this->configureGoogle($w);
        $admin = fn () => $this->actingOnBranch($w['admin'], $w['branch']);

        // not connected yet: cannot be chosen
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], 'teacher/learning/online-classes'), $this->payload($w, ['provider' => 'google_meet', 'join_url' => null]))->assertSessionHasErrors('provider');

        // the connect redirect goes to Google with offline access and a state
        $response = $admin()->get($this->branchUrl($w['branch'], 'learning/integrations/google/connect'));
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $q);
        $this->assertSame('google_client', $q['client_id']);
        $this->assertSame('offline', $q['access_type']);
        $this->assertSame('code', $q['response_type']);
        $this->assertStringContainsString('calendar.events', $q['scope']);
        $this->assertSame($this->branchUrl($w['branch'], 'learning/integrations/google/callback'), $q['redirect_uri']);

        // a wrong state is rejected and nothing is exchanged
        $admin()->get($this->branchUrl($w['branch'], 'learning/integrations/google/callback?code=abc&state=forged'))->assertSessionHas('warning');
        Http::assertNothingSent();

        Http::fake(function (HttpRequest $request) {
            $url = $request->url();
            if ($url === 'https://oauth2.googleapis.com/token') {
                parse_str($request->body(), $b);
                if (($b['grant_type'] ?? '') === 'authorization_code') {
                    return Http::response(['access_token' => 'g-access-1', 'refresh_token' => 'g-refresh', 'expires_in' => 3600, 'id_token' => $this->idToken('school@example.com')]);
                }

                return Http::response(['access_token' => 'g-access-2', 'expires_in' => 3600]);
            }
            if ($request->method() === 'POST' && str_starts_with($url, 'https://www.googleapis.com/calendar/v3/calendars/primary/events?')) {
                return Http::response(['id' => 'evt123', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij', 'htmlLink' => 'https://calendar.google.com/event?eid=1']);
            }
            if (str_starts_with($url, 'https://www.googleapis.com/calendar/v3/calendars/primary/events/evt123') && in_array($request->method(), ['PATCH', 'DELETE'], true)) {
                return Http::response($request->method() === 'DELETE' ? '' : ['id' => 'evt123'], $request->method() === 'DELETE' ? 204 : 200);
            }

            return Http::response(['error' => ['message' => 'unexpected '.$request->method().' '.$url]], 500);
        });

        // the real state from the session completes the connection
        $state = $q['state'];
        $this->actingOnBranch($w['admin'], $w['branch'])->withSession(['google_meet_state' => $state])
            ->get($this->branchUrl($w['branch'], 'learning/integrations/google/callback?code=abc&state='.$state))->assertSessionHas('success');
        $integration = $this->inBranch($w['branch'], fn () => OnlineClassIntegration::query()->where('provider', 'google_meet')->sole());
        $this->assertSame('g-refresh', $integration->token('refresh_token'));
        $this->assertSame('school@example.com', $integration->account_label);
        $this->assertStringNotContainsString('g-refresh', (string) DB::table('online_class_integrations')->where('provider', 'google_meet')->value('tokens'));
        $admin()->get($this->branchUrl($w['branch'], 'learning/integrations'))->assertOk()->assertSee('school@example.com')->assertDontSee('GOOGLE-TOP-SECRET')->assertDontSee('g-refresh');

        // scheduling creates an event with a Meet conference
        $class = $this->schedule($w, ['provider' => 'google_meet', 'join_url' => null, 'title' => 'Meet physics']);
        $this->assertSame('synced', $class->sync_status);
        $this->assertSame('evt123', $class->external_id);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $class->join_url);
        $this->assertSame('abc-defg-hij', $class->meeting_id);
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_contains($r->url(), 'conferenceDataVersion=1')
            && $r->hasHeader('Authorization', 'Bearer g-access-1') && $r['summary'] === 'Meet physics'
            && $r['conferenceData']['createRequest']['conferenceSolutionKey']['type'] === 'hangoutsMeet'
            && $r['start']['dateTime'] === '2026-03-16T10:00:00+06:00' && $r['start']['timeZone'] === 'Asia/Dhaka');

        // an expired access token is refreshed with the refresh token before the next call
        $this->inBranch($w['branch'], fn () => OnlineClassIntegration::query()->where('provider', 'google_meet')->sole()->putTokens(['expires_at' => 1]));
        $this->actingOnBranch($w['teacher'], $w['branch'])->put($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}"), $this->payload($w, [
            'provider' => 'google_meet', 'join_url' => null, 'title' => 'Meet physics', 'starts_at' => '2026-03-16T14:00', 'ends_at' => '2026-03-16T14:45',
        ]))->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'PATCH' && str_contains($r->url(), '/events/evt123') && $r->hasHeader('Authorization', 'Bearer g-access-2'));
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://oauth2.googleapis.com/token' && str_contains($r->body(), 'grant_type=refresh_token') && str_contains($r->body(), 'refresh_token=g-refresh'));

        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/cancel"), ['reason' => 'Holiday'])->assertSessionHasNoErrors();
        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/evt123'));
        $this->assertSame('cancelled', $class->fresh()->status);
    }

    public function test_students_and_parents_see_only_their_classes_and_the_link_opens_shortly_before_the_start(): void
    {
        $w = $this->world();
        $class = $this->schedule($w, ['title' => 'Algebra revision']);
        $this->schedule($w, ['title' => 'Other section class', 'section_id' => $w['setup']['nextSection']->id, 'school_class_id' => $w['setup']['next']->id, 'subject_id' => null], $w['other']);

        $studentPage = fn ($u) => $this->actingOnBranch($u, $w['branch'])->get($this->branchUrl($w['branch'], 'student/online-classes'));
        // a day before: scheduled, but no join button yet
        $studentPage($w['studentA'])->assertOk()->assertSee('Algebra revision')->assertDontSee('Other section class')->assertDontSee('https://meet.example.com/abc')->assertSee('15 minutes');
        $studentPage($w['studentC'])->assertOk()->assertSee('Other section class')->assertDontSee('Algebra revision');

        $this->travelTo(now()->setDate(2026, 3, 16)->setTime(9, 50));
        $studentPage($w['studentA'])->assertOk()->assertSee('https://meet.example.com/abc')->assertSee('Join class')->assertSee('1234');
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/online-classes"))->assertOk()->assertSee('Algebra revision');
        // a parent cannot look at someone else's child
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['c']->id}/online-classes"))->assertNotFound();

        // after the class: attendance and the recording show up in the history
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/attendance"), ['status' => [$w['a']->id => 'present', $w['c']->id => 'present']])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(1, $this->inBranch($w['branch'], fn () => \App\Models\Learning\OnlineClassAttendance::query()->count()), 'only students of the class can be marked');
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/recording"), ['recording_url' => 'https://drive.example.com/rec1'])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/complete"))->assertSessionHasNoErrors();
        $studentPage($w['studentA'])->assertOk()->assertSee('https://drive.example.com/rec1')->assertSee('Present')->assertDontSee('Join class');
        // …and counts towards the learning progress (1 of 1 classes attended)
        $this->travelTo(now()->setDate(2026, 3, 16)->setTime(11, 0));
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($this->branchUrl($w['branch'], 'student/learning'))->assertOk()->assertSee('Class attendance')->assertSee('100% (1/1)');
        $this->actingOnBranch($w['parentA'], $w['branch'])->get($this->branchUrl($w['branch'], "parent/children/{$w['a']->id}/learning"))->assertOk()->assertSee('100% (1/1)');
        // the other teacher may not touch it
        $this->actingOnBranch($w['other'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/attendance"), ['status' => [$w['a']->id => 'absent']])->assertNotFound();
        $this->actingOnBranch($w['other'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/cancel"))->assertNotFound();
    }

    public function test_zoom_cloud_recording_can_be_fetched_when_the_class_is_completed(): void
    {
        $w = $this->world();
        $this->fakeZoom();
        $this->configureZoom($w);
        $class = $this->schedule($w, ['provider' => 'zoom', 'join_url' => null]);

        $this->actingOnBranch($w['teacher'], $w['branch'])->post($this->branchUrl($w['branch'], "teacher/learning/online-classes/{$class->id}/complete"))->assertSessionHasNoErrors();
        $this->assertSame('https://zoom.us/rec/share/abc', $class->fresh()->recording_url);
    }

    public function test_permissions_protect_scheduling_and_the_integration_settings(): void
    {
        $w = $this->world();
        $url = fn ($p) => $this->branchUrl($w['branch'], $p);

        // students and parents have no back-office or teacher screens
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($url('learning/online-classes'))->assertForbidden();
        $this->actingOnBranch($w['studentA'], $w['branch'])->get($url('teacher/learning/online-classes'))->assertForbidden();
        $this->actingOnBranch($w['parentA'], $w['branch'])->post($url('learning/online-classes'), $this->payload($w))->assertForbidden();
        // teachers cannot configure integrations (no back-office screen)
        $this->actingOnBranch($w['teacher'], $w['branch'])->get($url('learning/integrations'))->assertForbidden();
        $this->actingOnBranch($w['teacher'], $w['branch'])->put($url('learning/integrations/zoom'), ['client_secret' => 'x'])->assertForbidden();
        // a principal may view but not change the settings
        $principal = $this->branchUser($w['branch'], 'principal');
        $this->actingOnBranch($principal, $w['branch'])->put($url('learning/integrations/zoom'), ['client_secret' => 'x'])->assertForbidden();
        // the admin can; an unknown provider is a 404
        $this->actingOnBranch($w['admin'], $w['branch'])->get($url('learning/integrations'))->assertOk()->assertSee('Zoom')->assertSee('Google Meet');
        $this->actingOnBranch($w['admin'], $w['branch'])->put($url('learning/integrations/skype'), [])->assertNotFound();
        // integrations of one branch are invisible to another
        $this->configureZoom($w);
        $branch2 = $this->makeBranch(null);
        $admin2 = $this->branchUser($branch2, 'branch-admin');
        $this->actingOnBranch($admin2, $branch2)->get($this->branchUrl($branch2, 'learning/integrations'))->assertOk()->assertDontSee('value="zoom_client"', false);
        $this->assertSame(0, $this->inBranch($branch2, fn () => OnlineClassIntegration::query()->count()));
    }
}
