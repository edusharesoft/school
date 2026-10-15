<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Models\Students\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_creates_student_guardian_and_enrollment_with_generated_ids(): void
    {
        $branch = $this->makeBranch(attributes: ['code' => 'BR01']);
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)
            ->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['admission_date' => '2026-01-10']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $student = $this->inBranch($branch, fn () => Student::query()->with(['guardians', 'enrollments'])->firstOrFail());
        $this->assertSame('BR01-260001', $student->student_code);
        $this->assertSame(1, $student->enrollments->first()->roll_no);
        $this->assertSame('father', $student->guardians->first()->pivot->relation);
        $this->assertTrue((bool) $student->guardians->first()->pivot->is_primary);

        $this->actingOnBranch($admin, $branch)
            ->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'Second', 'admission_date' => '2026-01-11']));
        $codes = $this->inBranch($branch, fn () => Student::query()->orderBy('id')->pluck('student_code')->all());
        $this->assertSame(['BR01-260001', 'BR01-260002'], $codes);
    }

    public function test_siblings_share_one_guardian_record(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup));
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'Sister']));

        $this->assertSame(1, $this->inBranch($branch, fn () => Guardian::query()->count()));
        $this->assertSame(2, $this->inBranch($branch, fn () => Guardian::query()->first()->students()->count()));
    }

    public function test_student_cannot_be_enrolled_twice_in_one_session(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $this->actingOnBranch($this->branchUser($branch, 'branch-admin'), $branch)
            ->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup));

        $student = $this->inBranch($branch, fn () => Student::query()->firstOrFail());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->inBranch($branch, fn () => app(\App\Services\Students\StudentService::class)->enroll($student, [
            'academic_session_id' => $setup['session']->id, 'school_class_id' => $setup['class']->id, 'section_id' => $setup['sectionB']->id,
        ]));
    }

    public function test_roll_numbers_are_unique_per_section_and_session(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['roll_no' => 5]))
            ->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'B', 'roll_no' => 5,
            'guardians' => [['phone' => '+8801711000002']]]))
            ->assertSessionHasErrors('roll_no');

        // Next automatic roll follows the highest existing one.
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'C',
            'guardians' => [['phone' => '+8801711000003']]]));
        $rolls = $this->inBranch($branch, fn () => StudentEnrollment::query()->orderBy('roll_no')->pluck('roll_no')->all());
        $this->assertSame([5, 6], $rolls);
    }

    public function test_full_section_rejects_new_admission(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $this->inBranch($branch, fn () => $setup['sectionA']->update(['capacity' => 1]));
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup))->assertSessionHasNoErrors();
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'Late']))
            ->assertSessionHasErrors('section_id');
    }

    public function test_group_is_required_for_group_classes(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $this->inBranch($branch, fn () => $setup['class']->update(['has_groups' => true]));
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup))
            ->assertSessionHasErrors('study_group_id');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['study_group_id' => $setup['group']->id]))
            ->assertSessionHasNoErrors();
    }

    public function test_student_search_by_name_id_class_and_roll(): void
    {
        $branch = $this->makeBranch(attributes: ['code' => 'BR02']);
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'Nusrat Jahan', 'gender' => 'female']));
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'Tanvir Ahmed', 'section_id' => $setup['sectionB']->id]));

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'students?q=Nusrat'))->assertSee('Nusrat Jahan')->assertDontSee('Tanvir Ahmed');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "students?class_id={$setup['class']->id}&section_id={$setup['sectionB']->id}"))
            ->assertSee('Tanvir Ahmed')->assertDontSee('Nusrat Jahan');
        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, "students?class_id={$setup['class']->id}&roll_no=1&section_id={$setup['sectionA']->id}"))
            ->assertSee('Nusrat Jahan');
    }

    public function test_student_of_another_branch_is_not_found(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $setupB = $this->academicSetup($b);
        $this->actingOnBranch($this->branchUser($b, 'branch-admin'), $b)->post($this->branchUrl($b, 'students'), $this->admissionPayload($setupB));
        $foreign = $this->inBranch($b, fn () => Student::query()->firstOrFail());

        $adminA = $this->branchUser($a, 'branch-admin');
        $this->actingOnBranch($adminA, $a)->get($this->branchUrl($a, "students/{$foreign->id}"))->assertNotFound();
        $this->actingOnBranch($adminA, $a)->patch($this->branchUrl($a, "students/{$foreign->id}/status"), ['status' => 'withdrawn'])->assertNotFound();
    }

    public function test_health_notes_are_hidden_without_permission(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $this->actingOnBranch($this->branchUser($branch, 'branch-admin'), $branch)
            ->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['medical_notes' => 'Asthma inhaler in bag']));
        $student = $this->inBranch($branch, fn () => Student::query()->firstOrFail());

        $this->assertNotSame('Asthma inhaler in bag', \DB::table('students')->value('medical_notes'), 'Notes must be encrypted at rest.');

        $accountant = $this->branchUser($branch, 'accountant'); // students.view but not students.view-health
        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, "students/{$student->id}"))
            ->assertOk()->assertDontSee('Asthma inhaler in bag');

        $principal = $this->branchUser($branch, 'principal'); // has students.view-health
        $this->actingOnBranch($principal, $branch)->get($this->branchUrl($branch, "students/{$student->id}"))->assertSee('Asthma inhaler in bag');
    }

    public function test_withdrawal_closes_the_current_enrollment(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup));
        $student = $this->inBranch($branch, fn () => Student::query()->firstOrFail());

        $this->actingOnBranch($admin, $branch)->patch($this->branchUrl($branch, "students/{$student->id}/status"), ['status' => 'withdrawn'])->assertRedirect();

        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'withdrawn']);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id, 'status' => 'withdrawn']);
    }

    public function test_promotion_moves_students_to_next_class_and_keeps_history(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup));
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'Two']));

        $next = $this->inBranch($branch, fn () => \App\Models\Academic\AcademicSession::query()->create([
            'academic_year_id' => $setup['session']->academic_year_id, 'name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'status' => 'open']));
        $ids = $this->inBranch($branch, fn () => StudentEnrollment::query()->pluck('id')->all());

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students/promotion'), [
            'enrollment_ids' => $ids, 'target_session_id' => $next->id, 'target_class_id' => $setup['next']->id, 'outcome' => 'promoted',
        ])->assertSessionHas('success');

        $this->inBranch($branch, function () use ($next, $setup) {
            $this->assertSame(2, StudentEnrollment::query()->where('academic_session_id', $next->id)->where('school_class_id', $setup['next']->id)->count());
            $this->assertSame(2, StudentEnrollment::query()->where('status', 'promoted')->count());
            $this->assertSame([1, 2], StudentEnrollment::query()->where('academic_session_id', $next->id)->orderBy('roll_no')->pluck('roll_no')->all());
        });

        // Running it again skips everyone (already enrolled in target session).
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students/promotion'), [
            'enrollment_ids' => $ids, 'target_session_id' => $next->id, 'target_class_id' => $setup['next']->id, 'outcome' => 'promoted',
        ]);
        $this->assertSame(4, $this->inBranch($branch, fn () => StudentEnrollment::query()->count()));
    }

    public function test_documents_are_private_and_branch_bound(): void
    {
        Storage::fake('local');
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution);
        $b = $this->makeBranch($institution);
        $setup = $this->academicSetup($a);
        $admin = $this->branchUser($a, 'branch-admin');
        $this->actingOnBranch($admin, $a)->post($this->branchUrl($a, 'students'), $this->admissionPayload($setup));
        $student = $this->inBranch($a, fn () => Student::query()->firstOrFail());

        $this->actingOnBranch($admin, $a)->post($this->branchUrl($a, "students/{$student->id}/documents"), [
            'type' => 'birth_certificate', 'title' => 'Birth certificate', 'file' => UploadedFile::fake()->create('bc.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $doc = $this->inBranch($a, fn () => $student->documents()->firstOrFail());
        $this->assertStringStartsWith("tenants/{$a->institution_id}/{$a->id}/students/", $doc->path);
        Storage::disk('local')->assertExists($doc->path);

        $this->actingOnBranch($admin, $a)->get($this->branchUrl($a, "students/{$student->id}/documents/{$doc->id}"))->assertOk();

        $adminB = $this->branchUser($b, 'branch-admin');
        $this->actingOnBranch($adminB, $b)->get($this->branchUrl($b, "students/{$student->id}/documents/{$doc->id}"))->assertNotFound();
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        Storage::fake('local');
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup));
        $student = $this->inBranch($branch, fn () => Student::query()->firstOrFail());

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, "students/{$student->id}/documents"), [
            'type' => 'other', 'title' => 'Evil', 'file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('file');
    }

    public function test_csv_import_validates_every_row_before_saving(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');

        $header = 'name,gender,date_of_birth,class_code,section,guardian_name,guardian_phone,guardian_relation,roll_no';
        $bad = $header."\nGood Kid,male,2014-01-01,VI,A,Dad,+8801711111111,father,1\nBad Kid,robot,2014-01-01,XX,A,Dad,+8801711111112,father,2\n";
        $good = $header."\nGood Kid,male,2014-01-01,VI,A,Dad,+8801711111111,father,1\nAnother,female,2014-02-01,VI,B,Mum,+8801711111112,mother,\n";

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students/import'), [
            'academic_session_id' => $setup['session']->id, 'dry_run' => 0,
            'file' => UploadedFile::fake()->createWithContent('bad.csv', $bad),
        ])->assertSessionHas('import_result', fn ($r) => ! $r['ok'] && isset($r['errors'][3]));
        $this->assertSame(0, $this->inBranch($branch, fn () => Student::query()->count()), 'Nothing may be saved when a row fails.');

        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students/import'), [
            'academic_session_id' => $setup['session']->id, 'dry_run' => 0,
            'file' => UploadedFile::fake()->createWithContent('good.csv', $good),
        ])->assertSessionHas('import_result', fn ($r) => $r['ok'] && $r['imported'] === 2);
        $this->assertSame(2, $this->inBranch($branch, fn () => Student::query()->count()));
    }

    public function test_student_export_is_csv(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $admin = $this->branchUser($branch, 'branch-admin');
        $this->actingOnBranch($admin, $branch)->post($this->branchUrl($branch, 'students'), $this->admissionPayload($setup, ['name' => 'Export Me']));

        $response = $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'students/export'));
        $response->assertOk();
        $this->assertStringContainsString('Export Me', $response->streamedContent());
    }

    public function test_students_api_is_scoped_and_hides_sensitive_fields(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution, ['subdomain' => 'branch1']);
        $b = $this->makeBranch($institution, ['subdomain' => 'branch2']);
        $setupA = $this->academicSetup($a);
        $setupB = $this->academicSetup($b);
        $this->actingOnBranch($this->branchUser($a, 'branch-admin'), $a)->post($this->branchUrl($a, 'students'), $this->admissionPayload($setupA, ['medical_notes' => 'secret']));
        $this->actingOnBranch($this->branchUser($b, 'branch-admin'), $b)->post($this->branchUrl($b, 'students'), $this->admissionPayload($setupB, ['name' => 'Other Branch Kid']));
        $this->app['auth']->forgetGuards();

        $this->branchUser($a, 'principal', ['email' => 'p@test.bd']);
        $token = $this->postJson('http://branch1.localhost/api/v1/auth/login', ['login' => 'p@test.bd', 'password' => 'password'])->json('data.token');

        $this->withToken($token)->getJson('http://branch1.localhost/api/v1/students')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.current_enrollment.roll_no', 1)
            ->assertJsonMissingPath('data.0.medical_notes')->assertDontSee('Other Branch Kid');
    }
}
