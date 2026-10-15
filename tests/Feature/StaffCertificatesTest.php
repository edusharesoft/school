<?php

namespace Tests\Feature;

use App\Models\HR\StaffCertificate;
use App\Models\Students\Certificate;
use App\Services\Documents\PdfService;
use App\Services\HR\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Part 6e — experience / service certificates of staff. mPDF is replaced by a recorder. */
class StaffCertificatesTest extends TestCase
{
    use RefreshDatabase;

    protected PdfService $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 20)->setTime(10, 0));
        Storage::fake('public');
        Storage::fake('local');
        $this->pdf = new class extends PdfService
        {
            public array $rendered = [];

            public function available(): bool
            {
                return true;
            }

            public function qrAvailable(): bool
            {
                return true;
            }

            public function render(string $html, array $options = []): string
            {
                $this->rendered[] = ['html' => $html, 'options' => $options];

                return '%PDF-1.4 recorded';
            }

            public function last(): array
            {
                return end($this->rendered) ?: ['html' => '', 'options' => []];
            }
        };
        $this->app->instance(PdfService::class, $this->pdf);
    }

    protected function world(): array
    {
        $branch = $this->makeBranch(null, ['principal_name' => 'Prof. Rahman']);
        $admin = $this->branchUser($branch, 'branch-admin');
        $working = $this->makeEmployee($branch, ['name' => 'Rafiq Ahmed', 'joining_date' => '2019-02-01']);
        $left = $this->makeEmployee($branch, ['name' => 'Salma Begum', 'joining_date' => '2017-03-01']);
        $this->inBranch($branch, fn () => app(EmployeeService::class)->changeStatus($left, 'resigned', '2026-05-31', 'Moved abroad', $admin));

        return compact('branch', 'admin', 'working', 'left');
    }

    protected function url(array $w, string $path): string
    {
        return $this->branchUrl($w['branch'], $path);
    }

    public function test_service_certificate_is_only_for_working_staff_and_experience_only_for_those_who_left(): void
    {
        $w = $this->world();
        $issue = fn ($employee, array $body) => $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w, "hr/employees/{$employee->id}/certificates"), $body);

        $issue($w['left'], ['type' => 'service'])->assertSessionHasErrors('type');
        $issue($w['working'], ['type' => 'experience'])->assertSessionHasErrors('type');
        $issue($w['working'], ['type' => 'bogus'])->assertSessionHasErrors('type');
        $this->assertSame(0, $this->inBranch($w['branch'], fn () => StaffCertificate::query()->count()));

        $issue($w['working'], ['type' => 'service', 'purpose' => 'Bank loan'])->assertSessionHasNoErrors()->assertRedirect();
        $issue($w['left'], ['type' => 'experience', 'conduct' => 'Excellent'])->assertSessionHasNoErrors();

        $rows = $this->inBranch($w['branch'], fn () => StaffCertificate::query()->orderBy('id')->get());
        $this->assertCount(2, $rows);
        $this->assertMatchesRegularExpression('/-SV-26-0001$/', $rows[0]->serial_no);
        $this->assertMatchesRegularExpression('/-EX-26-0001$/', $rows[1]->serial_no);
        $this->assertSame('2026-05-31', $rows[1]->data['leaving_date']);
        $this->assertSame('Bank loan', $rows[0]->data['purpose']);
        $this->assertArrayNotHasKey('salary', $rows[0]->data);
        $this->assertNotSame($rows[0]->verification_code, $rows[1]->verification_code);
    }

    public function test_pdf_prints_the_facts_and_a_revoked_certificate_is_watermarked(): void
    {
        $w = $this->world();
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w, "hr/employees/{$w['left']->id}/certificates"), ['type' => 'experience', 'conduct' => 'Excellent'])->assertSessionHasNoErrors();
        $cert = $this->inBranch($w['branch'], fn () => StaffCertificate::query()->sole());

        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w, "hr/certificates/{$cert->id}/pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $html = $this->pdf->last()['html'];
        $this->assertStringContainsString('Salma Begum', $html);
        $this->assertStringContainsString($cert->serial_no, $html);
        $this->assertStringContainsString($cert->verification_code, $html);
        $this->assertNull($this->pdf->last()['options']['watermark']);

        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w, "hr/certificates/{$cert->id}/revoke"), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w, "hr/certificates/{$cert->id}/revoke"), ['reason' => 'Wrong designation'])->assertSessionHasNoErrors();
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w, "hr/certificates/{$cert->id}/revoke"), ['reason' => 'Again'])->assertSessionHasErrors('reason');
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w, "hr/certificates/{$cert->id}/pdf"))->assertOk();
        $this->assertSame(__('REVOKED'), $this->pdf->last()['options']['watermark']);
    }

    public function test_register_lists_certificates_and_the_public_page_verifies_the_code(): void
    {
        $w = $this->world();
        $this->actingOnBranch($w['admin'], $w['branch'])->post($this->url($w, "hr/employees/{$w['working']->id}/certificates"), ['type' => 'service'])->assertSessionHasNoErrors();
        $cert = $this->inBranch($w['branch'], fn () => StaffCertificate::query()->sole());

        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w, 'hr/certificates'))->assertOk()->assertSee($cert->serial_no)->assertSee('Rafiq Ahmed');
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w, 'hr/certificates?type=experience'))->assertOk()->assertDontSee($cert->serial_no);
        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->url($w, "hr/employees/{$w['working']->id}"))->assertOk();

        auth()->logout();
        $this->get($this->url($w, 'certificates/verify?code='.$cert->verification_code))->assertOk()->assertSee('Rafiq Ahmed')->assertSee($cert->serial_no);
        $this->get($this->url($w, 'certificates/verify?code=ZZZZZZZZZZ'))->assertOk()->assertDontSee('Rafiq Ahmed');

        // Codes never collide with student certificates.
        $this->assertSame(0, $this->inBranch($w['branch'], fn () => Certificate::withoutGlobalScopes()->where('verification_code', $cert->verification_code)->count()));
    }

    public function test_permissions_and_branch_isolation(): void
    {
        $w = $this->world();
        $other = $this->makeBranch();
        $hr = $this->branchUser($w['branch'], 'hr-manager');
        $teacher = $this->branchUser($w['branch'], 'teacher');
        $otherAdmin = $this->branchUser($other, 'branch-admin');

        $this->actingOnBranch($hr, $w['branch'])->post($this->url($w, "hr/employees/{$w['working']->id}/certificates"), ['type' => 'service'])->assertSessionHasNoErrors();
        $cert = $this->inBranch($w['branch'], fn () => StaffCertificate::query()->sole());

        $this->actingOnBranch($teacher, $w['branch'])->get($this->url($w, 'hr/certificates'))->assertForbidden();
        $this->actingOnBranch($teacher, $w['branch'])->post($this->url($w, "hr/employees/{$w['working']->id}/certificates"), ['type' => 'service'])->assertForbidden();
        $this->actingOnBranch($teacher, $w['branch'])->get($this->url($w, "hr/certificates/{$cert->id}/pdf"))->assertForbidden();

        $this->actingOnBranch($otherAdmin, $other)->get($this->branchUrl($other, "hr/certificates/{$cert->id}/pdf"))->assertNotFound();
        $this->actingOnBranch($otherAdmin, $other)->post($this->branchUrl($other, "hr/certificates/{$cert->id}/revoke"), ['reason' => 'x y z'])->assertNotFound();
        $this->actingOnBranch($otherAdmin, $other)->get($this->branchUrl($other, 'hr/certificates'))->assertOk()->assertDontSee($cert->serial_no);
    }
}
