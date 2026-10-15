<?php

namespace Tests\Feature;

use App\Models\Admissions\AdmissionApplication;
use App\Models\Admissions\AdmissionCampaign;
use App\Models\Branch;
use App\Models\HR\StaffBranchAssignment;
use App\Models\Institution;
use App\Models\Students\Certificate;
use App\Models\Students\Student;
use App\Models\Students\StudentDocument;
use App\Models\Students\StudentTransfer;
use App\Models\User;
use App\Services\Admissions\AdmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(10, 0));
        Storage::fake('local');
    }

    protected function campaign(Branch $branch, array $setup, array $overrides = []): AdmissionCampaign
    {
        return $this->inBranch($branch, fn () => AdmissionCampaign::query()->create(array_merge([
            'academic_session_id' => $setup['session']->id, 'school_class_id' => $setup['class']->id, 'title' => 'Six mid-year',
            'starts_on' => '2026-03-01', 'ends_on' => '2026-03-31', 'seats' => 2, 'is_online' => true, 'has_test' => false,
            'admission_fee' => 0, 'fee_before_admission' => false, 'required_documents' => ['birth_certificate'],
            'form_settings' => ['birth_registration_no' => 'required'], 'status' => 'open',
        ], $overrides)));
    }

    protected function form(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ayesha Siddika', 'gender' => 'female', 'date_of_birth' => '2014-05-01', 'birth_registration_no' => '20141234567890123',
            'phone' => '01711-223344', 'present_address' => 'Mirpur, Dhaka', 'guardian_name' => 'Abdul Karim', 'guardian_phone' => '01811223344',
            'guardian_relation' => 'father',
            'documents' => ['birth_certificate' => UploadedFile::fake()->create('birth.pdf', 120, 'application/pdf')],
        ], $overrides);
    }

    /** Offline applications via the service (fast fixture). */
    protected function apply(Branch $branch, AdmissionCampaign $campaign, string $name, string $reg, ?float $marks = null, bool $absent = false): AdmissionApplication
    {
        return $this->inBranch($branch, function () use ($campaign, $name, $reg) {
            return app(AdmissionService::class)->submit($campaign, collect($this->form(['name' => $name, 'birth_registration_no' => $reg]))->except('documents')->all(), [], null, 'offline');
        });
    }

    public function test_admission_round_is_created_with_form_settings_and_listed_publicly(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'admissions/campaigns'), [
            'title' => 'Class Six 2026', 'academic_session_id' => $setup['session']->id, 'school_class_id' => $setup['class']->id,
            'starts_on' => '2026-03-01', 'ends_on' => '2026-03-31', 'seats' => 40, 'is_online' => 1, 'has_test' => 1,
            'test_at' => '2026-04-05T10:00', 'test_total_marks' => 50, 'test_pass_marks' => 20, 'max_age' => 13, 'admission_fee' => 1500,
            'required_documents' => ['birth_certificate'], 'form_settings' => ['photo' => 'required', 'religion' => 'hidden', 'bogus' => 'required'],
            'status' => 'open',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $campaign = $this->inBranch($branch, fn () => AdmissionCampaign::query()->firstOrFail());
        $this->assertTrue($campaign->requires('photo'));
        $this->assertFalse($campaign->shows('religion'));
        $this->assertArrayNotHasKey('bogus', $campaign->form_settings);

        $this->get($this->branchUrl($branch, 'admission'))->assertOk()->assertSee('Class Six 2026');
        $this->get($this->branchUrl($branch, "admission/{$campaign->id}/apply"))->assertOk()
            ->assertSee('name="photo"', false)->assertDontSee('name="religion"', false);
    }

    public function test_online_application_gets_a_number_documents_and_a_private_status_lookup(): void
    {
        $branch = $this->makeBranch(attributes: ['code' => 'BR01']);
        $campaign = $this->campaign($branch, $this->academicSetup($branch));

        $this->post($this->branchUrl($branch, "admission/{$campaign->id}/apply"), $this->form())
            ->assertRedirect()->assertSessionHasNoErrors();

        $application = $this->inBranch($branch, fn () => AdmissionApplication::query()->with('documents')->firstOrFail());
        $this->assertSame('BR01-A26-00001', $application->application_no);
        $this->assertSame('online', $application->source);
        $this->assertSame('01711223344', $application->phone);
        $this->assertCount(1, $application->documents);
        Storage::disk('local')->assertExists($application->documents->first()->path);

        $this->get($this->branchUrl($branch, 'admission/status?application_no=BR01-A26-00001&phone=01700000000'))
            ->assertOk()->assertDontSee('Ayesha Siddika');
        $this->get($this->branchUrl($branch, 'admission/status?application_no=br01-a26-00001&phone=01711223344'))
            ->assertOk()->assertSee('Ayesha Siddika');
    }

    public function test_required_documents_deadline_duplicates_and_age_are_enforced(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $campaign = $this->campaign($branch, $setup, ['min_age' => 10, 'max_age' => 13]);
        $url = $this->branchUrl($branch, "admission/{$campaign->id}/apply");

        $this->post($url, $this->form(['documents' => []]))->assertSessionHasErrors('documents.birth_certificate');
        $this->post($url, $this->form(['date_of_birth' => '2019-01-01']))->assertSessionHasErrors('date_of_birth');
        $this->post($url, $this->form(['website' => 'http://spam.example']))->assertSessionHasErrors('website');

        $this->post($url, $this->form())->assertSessionHasNoErrors();
        $this->post($url, $this->form(['name' => 'Other Name']))->assertSessionHasErrors('name'); // same birth registration no.

        $closed = $this->campaign($branch, $setup, ['ends_on' => '2026-03-05']);
        $this->get($this->branchUrl($branch, "admission/{$closed->id}/apply"))->assertOk()->assertDontSee('name="guardian_name"', false);
        $this->post($this->branchUrl($branch, "admission/{$closed->id}/apply"), $this->form(['birth_registration_no' => '20149999999999999']))
            ->assertSessionHasErrors('campaign');

        $draft = $this->campaign($branch, $setup, ['status' => 'draft']);
        $this->get($this->branchUrl($branch, "admission/{$draft->id}/apply"))->assertNotFound();
    }

    public function test_merit_list_ranks_by_marks_fills_seats_keeps_a_waiting_list_and_promotes(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $campaign = $this->campaign($branch, $setup, ['has_test' => true, 'test_total_marks' => 50, 'test_pass_marks' => 20, 'seats' => 2]);
        $a = $this->apply($branch, $campaign, 'Alpha', '20140000000000001');
        $b = $this->apply($branch, $campaign, 'Bravo', '20140000000000002');
        $c = $this->apply($branch, $campaign, 'Charlie', '20140000000000003');
        $d = $this->apply($branch, $campaign, 'Delta', '20140000000000004');
        $e = $this->apply($branch, $campaign, 'Echo', '20140000000000005');
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "admissions/campaigns/{$campaign->id}/merit"))
            ->assertSessionHasErrors('merit'); // marks missing

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "admissions/campaigns/{$campaign->id}/marks"), ['rows' => [
            $a->id => ['marks' => 30], $b->id => ['marks' => 45], $c->id => ['marks' => 30], $d->id => ['marks' => 10], $e->id => ['absent' => 1],
        ]])->assertSessionHasNoErrors();

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "admissions/campaigns/{$campaign->id}/merit"))->assertSessionHasNoErrors();

        $fresh = fn ($x) => $this->inBranch($branch, fn () => $x->fresh());
        $this->assertSame(['selected', 1], [$fresh($b)->status, $fresh($b)->merit_position]);
        $this->assertSame(['selected', 2], [$fresh($a)->status, $fresh($a)->merit_position]); // tie → earlier application
        $this->assertSame(['waiting', 1], [$fresh($c)->status, $fresh($c)->waiting_position]);
        $this->assertSame('rejected', $fresh($d)->status);
        $this->assertSame('rejected', $fresh($e)->status);

        // A selected applicant drops out → the waiting list moves up.
        $this->actingOnBranch($admin, $branch)->patch($this->branchUrl($branch, "admissions/applications/{$a->id}/status"), ['status' => 'cancelled']);
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "admissions/campaigns/{$campaign->id}/merit/promote"))->assertSessionHasNoErrors();
        $this->assertSame('selected', $fresh($c)->status);
    }

    public function test_selected_applicant_is_admitted_after_the_fee_with_documents_copied(): void
    {
        $branch = $this->makeBranch(attributes: ['code' => 'BR01']);
        $setup = $this->academicSetup($branch);
        $campaign = $this->campaign($branch, $setup, ['admission_fee' => 2500, 'fee_before_admission' => true, 'seats' => 5]);
        $this->post($this->branchUrl($branch, "admission/{$campaign->id}/apply"), $this->form())->assertSessionHasNoErrors();
        $application = $this->inBranch($branch, fn () => AdmissionApplication::query()->firstOrFail());
        $admin = $this->branchUser($branch, 'branch-admin');
        $url = fn ($p) => $this->branchUrl($branch, "admissions/applications/{$application->id}/{$p}");

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "admissions/campaigns/{$campaign->id}/merit"))->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->post($url('admit'), ['section_id' => $setup['sectionA']->id])->assertSessionHasErrors('fee');

        $this->actingOnBranch($admin, $branch)->post($url('fee'), ['fee_status' => 'paid', 'fee_reference' => 'RCPT-001'])->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->post($url('admit'), ['section_id' => $setup['sectionA']->id])->assertRedirect();

        $student = $this->inBranch($branch, fn () => Student::query()->with(['enrollments', 'guardians'])->firstOrFail());
        $this->assertSame('BR01-260001', $student->student_code);
        $this->assertSame($setup['sectionA']->id, $student->enrollments->first()->section_id);
        $this->assertSame('01811223344', $student->guardians->first()->phone);
        $this->assertSame(1, $this->inBranch($branch, fn () => StudentDocument::query()->where('student_id', $student->id)->count()));
        $this->assertSame(['admitted', $student->id], [$this->inBranch($branch, fn () => $application->fresh()->status), $this->inBranch($branch, fn () => $application->fresh()->student_id)]);
    }

    public function test_offline_entry_and_branch_isolation(): void
    {
        $institution = Institution::factory()->create();
        $branch = $this->makeBranch($institution);
        $other = $this->makeBranch($institution);
        $campaign = $this->campaign($branch, $this->academicSetup($branch));
        $otherApplication = $this->apply($other, $this->campaign($other, $this->academicSetup($other)), 'Elsewhere', '20140000000000009');

        $officer = $this->branchUser($branch, 'admission-officer');
        $this->actingOnBranch($officer, $branch)->post($this->branchUrl($branch, 'admissions/applications'),
            $this->form(['admission_campaign_id' => $campaign->id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('offline', $this->inBranch($branch, fn () => AdmissionApplication::query()->firstOrFail()->source));

        $this->actingOnBranch($officer, $branch)->get($this->branchUrl($branch, "admissions/applications/{$otherApplication->id}"))->assertNotFound();
        $this->actingOnBranch($officer, $branch)->post($this->branchUrl($branch, "admissions/applications/{$otherApplication->id}/admit"), ['section_id' => 1])->assertNotFound();
    }

    public function test_transfer_between_branches_creates_a_record_in_the_target_and_closes_the_source(): void
    {
        $institution = Institution::factory()->create();
        $from = $this->makeBranch($institution);
        $to = $this->makeBranch($institution);
        $fromSetup = $this->academicSetup($from);
        $toSetup = $this->academicSetup($to);
        $student = $this->enrolStudent($from, $fromSetup, 'Moving Kid');

        $this->actingOnBranch($this->branchUser($from, 'branch-admin'), $from)
            ->post($this->branchUrl($from, "students/{$student->id}/transfer"), ['to_branch_id' => $to->id, 'reason' => 'Family moved to the other area'])
            ->assertSessionHasNoErrors();
        $transfer = $this->inBranch($from, fn () => StudentTransfer::query()->firstOrFail());

        // The source branch cannot accept its own request.
        $this->actingOnBranch($this->branchUser($from, 'branch-admin'), $from)
            ->post($this->branchUrl($from, "transfers/{$transfer->id}/accept"), ['academic_session_id' => 1, 'school_class_id' => 1, 'section_id' => 1])->assertNotFound();

        $this->actingOnBranch($this->branchUser($to, 'branch-admin'), $to)->post($this->branchUrl($to, "transfers/{$transfer->id}/accept"), [
            'academic_session_id' => $toSetup['session']->id, 'school_class_id' => $toSetup['class']->id, 'section_id' => $toSetup['sectionA']->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('transferred', $this->inBranch($from, fn () => $student->fresh()->status));
        $new = $this->inBranch($to, fn () => Student::query()->with('guardians')->firstOrFail());
        $this->assertSame('Moving Kid', $new->name);
        $this->assertCount(1, $new->guardians);
        $done = $this->inBranch($to, fn () => $transfer->fresh());
        $this->assertSame(['accepted', $new->id], [$done->status, $done->new_student_id]);
    }

    public function test_readmission_and_certificates_with_public_verification(): void
    {
        $branch = $this->makeBranch(attributes: ['code' => 'BR01']);
        $setup = $this->academicSetup($branch);
        $student = $this->enrolStudent($branch, $setup, 'Rahim Uddin');
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/certificates"), ['type' => 'study'])->assertRedirect();
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/certificates"), ['type' => 'transfer'])
            ->assertSessionHasErrors('type'); // still active
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/certificates"),
            ['type' => 'transfer', 'mark_left' => 1, 'reason' => 'Moving abroad'])->assertSessionHasNoErrors();

        $certificates = $this->inBranch($branch, fn () => Certificate::query()->orderBy('id')->get());
        $this->assertSame(['BR01-SC-26-0001', 'BR01-TC-26-0001'], $certificates->pluck('serial_no')->all());
        $this->assertSame('withdrawn', $this->inBranch($branch, fn () => $student->fresh()->status));

        $this->get($this->branchUrl($branch, 'certificates/verify?code='.$certificates[0]->verification_code))->assertOk()->assertSee('Rahim Uddin');
        $this->get($this->branchUrl($branch, 'certificates/verify?code=NOTREAL123'))->assertOk()->assertDontSee('Rahim Uddin');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "certificates/{$certificates[0]->id}/revoke"), ['reason' => 'Issued by mistake']);
        $this->get($this->branchUrl($branch, 'certificates/verify?code='.$certificates[0]->verification_code))->assertSee(__('This certificate has been revoked.'));

        // Re-admission in the same session reopens the closed enrollment (same ID, class, roll).
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/readmit"), [
            'academic_session_id' => $setup['session']->id, 'school_class_id' => $setup['class']->id, 'section_id' => $setup['sectionA']->id,
        ])->assertSessionHasNoErrors();
        $fresh = $this->inBranch($branch, fn () => $student->fresh('enrollments'));
        $this->assertSame('active', $fresh->status);
        $this->assertCount(1, $fresh->enrollments);
        $this->assertSame('enrolled', $fresh->enrollments->first()->status);
    }

    public function test_central_staff_assignment_grants_and_revokes_branch_access(): void
    {
        $institution = Institution::factory()->create();
        $home = $this->makeBranch($institution);
        $extra = $this->makeBranch($institution);
        $teacher = $this->branchUser($home, 'teacher', ['user_type' => \App\Enums\UserType::Teacher]);
        $employee = $this->makeEmployee($home, [], $teacher);
        $admin = User::factory()->create(['institution_id' => $institution->id]);
        $admin->assignRole('institution-admin');

        $this->assertFalse($teacher->canAccessBranch($extra));

        $this->actingCentral($admin)->post($this->centralUrl('super-admin/staff-assignments'), [
            'employee_id' => $employee->id, 'branch_id' => $extra->id, 'starts_on' => '2026-03-10', 'duty' => 'Physics XI',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($teacher->fresh()->canAccessBranch($extra));

        $assignment = tenant()->runCentral($institution, fn () => StaffBranchAssignment::query()->firstOrFail());
        $this->actingCentral($admin)->post($this->centralUrl("super-admin/staff-assignments/{$assignment->id}/end"))->assertSessionHasNoErrors();
        $this->assertFalse($teacher->fresh()->canAccessBranch($extra));
        $this->assertTrue($teacher->fresh()->canAccessBranch($home));
    }
}
