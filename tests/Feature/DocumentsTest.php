<?php

namespace Tests\Feature;

use App\Models\Academic\ClassSubject;
use App\Models\Academic\Subject;
use App\Models\Branch;
use App\Models\Documents\IdCard;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Models\Exams\ExaminationSubject;
use App\Models\Exams\GradingScale;
use App\Models\Students\Certificate;
use App\Models\User;
use App\Services\Documents\DocumentSettings;
use App\Services\Documents\PdfService;
use App\Services\Exams\ExaminationService;
use App\Services\Students\CertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Part 6d — PDF documents: certificates, ID cards (register, verification), admit cards and mark sheets. mPDF itself is replaced by a recorder. */
class DocumentsTest extends TestCase
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
        $branch = $this->makeBranch(null, ['principal_name' => 'Prof. Rahman', 'phone' => '01711000000', 'address' => '12 School Road, Dhaka']);
        $admin = $this->branchUser($branch, 'branch-admin');
        $setup = $this->academicSetup($branch);
        $students = [
            'a' => $this->enrolStudent($branch, $setup, 'Anika Rahman'),
            'b' => $this->enrolStudent($branch, $setup, 'Babul Hasan'),
            'c' => $this->enrolStudent($branch, $setup, 'Chandni Akter', $setup['sectionB']),
        ];

        return compact('branch', 'admin', 'setup', 'students');
    }

    protected function as(array $w, ?User $user = null): static
    {
        return $this->actingOnBranch($user ?? $w['admin'], $w['branch']);
    }

    protected function url(array $w, string $path): string
    {
        return $this->branchUrl($w['branch'], $path);
    }

    // ------------------------------------------------------------------ certificates

    public function test_certificate_pdf_has_the_wording_serial_qr_and_the_branch_signature(): void
    {
        $w = $this->world();
        $this->as($w)->put($this->url($w, 'documents/settings'), $this->settingsPayload(['signatory_name' => 'Dr. Karim', 'certificate_footer' => 'Computer generated'])
            + ['signature' => UploadedFile::fake()->image('sig.png', 300, 100), 'seal' => UploadedFile::fake()->image('seal.png', 200, 200)])->assertSessionHasNoErrors();

        $cert = $this->inBranch($w['branch'], fn () => app(CertificateService::class)->issue($w['students']['a'], 'study', [], $w['admin']));
        $response = $this->as($w)->get($this->url($w, "certificates/{$cert->id}/pdf"));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.4 recorded', $response->getContent());
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));

        $call = $this->pdf->last();
        $this->assertSame('L', $call['options']['orientation']);
        $this->assertNull($call['options']['watermark']);
        $html = $call['html'];
        $this->assertStringContainsString($cert->serial_no, $html);
        $this->assertStringContainsString('Anika Rahman', $html);
        $this->assertStringContainsString('Dr. Karim', $html);
        $this->assertStringContainsString('Computer generated', $html);
        $this->assertStringContainsString($cert->verification_code, $html);
        $this->assertStringContainsString('type="QR"', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'data:image/'));         // signature + seal are embedded
        $this->assertStringContainsString('border:2.4mm double', $html);                   // classic border

        // Revoked certificates carry a watermark.
        $this->inBranch($w['branch'], fn () => app(CertificateService::class)->revoke($cert->fresh(), 'issued by mistake'));
        $this->as($w)->get($this->url($w, "certificates/{$cert->id}/pdf"))->assertOk();
        $this->assertSame(__('REVOKED'), $this->pdf->last()['options']['watermark']);
    }

    public function test_achievement_certificate_needs_a_reason_and_prints_it(): void
    {
        $w = $this->world();
        $a = $w['students']['a'];
        $this->as($w)->post($this->url($w, "students/{$a->id}/certificates"), ['type' => 'achievement'])->assertSessionHasErrors('title');
        $this->as($w)->post($this->url($w, "students/{$a->id}/certificates"), ['type' => 'achievement', 'title' => 'First position in the science fair', 'details' => 'Inter-school round, Dhaka'])->assertSessionHasNoErrors();

        $cert = $this->inBranch($w['branch'], fn () => Certificate::query()->sole());
        $this->assertSame('AC', Certificate::CODES[$cert->type]);
        $this->assertStringContainsString('-AC-', $cert->serial_no);
        $this->as($w)->get($this->url($w, "certificates/{$cert->id}"))->assertOk()->assertSee('First position in the science fair')->assertSee('Inter-school round, Dhaka');
        $this->as($w)->get($this->url($w, "certificates/{$cert->id}/pdf"))->assertOk();
        $this->assertStringContainsString('CERTIFICATE OF ACHIEVEMENT', mb_strtoupper($this->pdf->last()['html']));
    }

    public function test_pdf_routes_need_permission_and_stay_inside_the_branch(): void
    {
        $w = $this->world();
        $cert = $this->inBranch($w['branch'], fn () => app(CertificateService::class)->issue($w['students']['a'], 'study', [], $w['admin']));
        $teacher = $this->branchUser($w['branch'], 'teacher');
        $this->as($w, $teacher)->get($this->url($w, "certificates/{$cert->id}/pdf"))->assertForbidden();

        $other = $this->makeBranch($w['branch']->institution);
        $otherAdmin = $this->branchUser($other, 'branch-admin');
        $this->actingOnBranch($otherAdmin, $other)->get($this->branchUrl($other, "certificates/{$cert->id}/pdf"))->assertNotFound();
    }

    public function test_pdf_service_explains_a_missing_package(): void
    {
        $service = new class extends PdfService
        {
            public function available(): bool
            {
                return false;
            }
        };
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mpdf/mpdf');
        $service->render('<p>x</p>');
    }

    public function test_photos_are_cropped_to_the_frame_instead_of_squeezed(): void
    {
        $dir = sys_get_temp_dir().'/photo-crop-'.uniqid();
        mkdir($dir);
        $wide = $dir.'/wide.png';
        $tall = $dir.'/tall.png';
        imagepng(imagecreatetruecolor(800, 400), $wide);
        imagepng(imagecreatetruecolor(300, 900), $tall);

        $size = function (?string $uri): array {
            $this->assertNotNull($uri);
            $info = getimagesizefromstring(base64_decode(substr($uri, strpos($uri, ',') + 1)));

            return [$info[0], $info[1]];
        };
        $ratio = 22 / 27;
        [$w, $h] = $size($this->pdf->image($wide, 320, $ratio));
        $this->assertEqualsWithDelta($ratio, $w / $h, 0.02);
        $this->assertLessThanOrEqual(320, max($w, $h));
        [$w, $h] = $size($this->pdf->image($tall, 320, $ratio));
        $this->assertEqualsWithDelta($ratio, $w / $h, 0.02);

        [$w, $h] = $size($this->pdf->image($wide, 320));         // no ratio: only shrunk, shape kept
        $this->assertSame([320, 160], [$w, $h]);
        $this->assertNull($this->pdf->image($dir.'/missing.png', 320, $ratio));

        @unlink($wide);
        @unlink($tall);
        @rmdir($dir);
    }

    // ------------------------------------------------------------------ settings

    protected function settingsPayload(array $over = []): array
    {
        return $over + [
            'signatory_name' => '', 'signatory_title' => 'Principal / Head Teacher', 'accent_color' => '#1d4ed8', 'certificate_style' => 'classic', 'certificate_footer' => '',
            'card_theme' => 'brand', 'card_validity_months' => 12, 'card_show_blood_group' => 1, 'card_show_guardian_phone' => 1, 'card_emergency_phone' => '', 'card_back_text' => 'Return to school if found.',
        ];
    }

    public function test_document_settings_are_validated_saved_per_branch_and_images_can_be_removed(): void
    {
        $w = $this->world();
        $teacher = $this->branchUser($w['branch'], 'teacher');
        $this->as($w, $teacher)->get($this->url($w, 'documents/settings'))->assertForbidden();

        $this->as($w)->get($this->url($w, 'documents/settings'))->assertOk()->assertSee('Prof. Rahman');
        $this->as($w)->put($this->url($w, 'documents/settings'), $this->settingsPayload(['accent_color' => 'blue', 'card_validity_months' => 0, 'certificate_style' => 'fancy']))
            ->assertSessionHasErrors(['accent_color', 'card_validity_months', 'certificate_style']);
        $this->as($w)->put($this->url($w, 'documents/settings'), $this->settingsPayload(['signature' => UploadedFile::fake()->create('virus.exe', 10)]))->assertSessionHasErrors('signature');

        $this->as($w)->put($this->url($w, 'documents/settings'), $this->settingsPayload(['signature' => UploadedFile::fake()->image('sig.png', 200, 80), 'card_validity_months' => 6, 'card_theme' => 'maroon']))->assertSessionHasNoErrors();
        $values = $this->inBranch($w['branch'], fn () => app(DocumentSettings::class)->all());
        $this->assertSame(6, $values['card_validity_months']);
        $this->assertSame('maroon', $values['card_theme']);
        $this->assertNotSame('', $values['signature_path']);
        Storage::disk('public')->assertExists($values['signature_path']);

        $this->as($w)->put($this->url($w, 'documents/settings'), $this->settingsPayload(['remove_signature' => 1]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($values['signature_path']);
        $this->assertSame('', $this->inBranch($w['branch'], fn () => app(DocumentSettings::class)->get('signature_path')));

        // Another branch keeps its own settings.
        $other = $this->makeBranch($w['branch']->institution);
        $this->assertSame(12, $this->inBranch($other, fn () => app(DocumentSettings::class)->get('card_validity_months')));
    }

    // ------------------------------------------------------------------ ID cards

    public function test_printing_issues_cards_once_and_reprints_keep_the_number(): void
    {
        $w = $this->world();
        $a = $w['students']['a'];
        $b = $w['students']['b'];
        $this->as($w)->get($this->url($w, 'id-cards'))->assertOk()->assertSee('Anika Rahman')->assertSee('Chandni Akter')->assertSee(__('not issued'));
        $this->as($w)->get($this->url($w, 'id-cards?class='.$w['setup']['class']->id.'&section='.$w['setup']['sectionB']->id))->assertOk()->assertSee('Chandni Akter')->assertDontSee('Anika Rahman');

        $print = fn (array $over = []) => $this->as($w)->post($this->url($w, 'id-cards/print'), $over + ['tab' => 'students', 'ids' => [$b->id, $a->id], 'layout' => 'sheet', 'sides' => 'both']);
        $print()->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $cards = $this->inBranch($w['branch'], fn () => IdCard::query()->orderBy('id')->get());
        $this->assertCount(2, $cards);
        // class / section / roll order, not the order of the ids: Anika (roll 1) was admitted first
        $this->assertSame([$a->id, $b->id], $cards->pluck('student_id')->all());
        $this->assertSame(['IDS-000001', 'IDS-000002'], $cards->pluck('card_no')->all());
        $this->assertSame('2027-06-30', $cards[0]->valid_until->toDateString());       // 12 months, end of month
        $this->assertSame([1, 1], $cards->pluck('printed_count')->all());

        $call = $this->pdf->last();
        $this->assertSame('A4', $call['options']['format']);
        $html = $call['html'];
        foreach ([$a->student_code, $b->student_code, 'Anika Rahman', 'Babul Hasan', $cards[0]->verification_code] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertStringContainsString('type="C128B"', $html);
        $this->assertStringContainsString('type="QR"', $html);
        $this->assertStringContainsString('/id/verify?code='.$cards[0]->verification_code, $html);
        $this->assertStringNotContainsString('Chandni', $html);
        $this->assertSame(2, substr_count($html, '<pagebreak') + 1);                    // fronts page + (mirrored) backs page

        // Printing again reuses the cards and counts the reprint.
        $print(['sides' => 'front', 'layout' => 'card', 'ids' => [$a->id]])->assertOk();
        $this->assertSame(2, $this->inBranch($w['branch'], fn () => IdCard::query()->count()));
        $this->assertSame(2, $this->inBranch($w['branch'], fn () => IdCard::query()->where('student_id', $a->id)->value('printed_count')));
        $call = $this->pdf->last();
        $this->assertSame([54.0, 85.6], $call['options']['format']);
        $this->assertStringNotContainsString('type="QR"', $call['html']);               // front only

        // Single card from the student page.
        $this->as($w)->get($this->url($w, "id-cards/students/{$b->id}/pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(2, $this->inBranch($w['branch'], fn () => IdCard::query()->where('student_id', $b->id)->value('printed_count')));
    }

    public function test_card_show_or_hide_options_and_staff_cards(): void
    {
        $w = $this->world();
        $this->as($w)->put($this->url($w, 'documents/settings'), $this->settingsPayload(['card_show_blood_group' => 0, 'card_show_guardian_phone' => 0, 'card_back_text' => 'Property of the school']))->assertSessionHasNoErrors();
        $a = $w['students']['a'];
        $this->inBranch($w['branch'], fn () => $a->forceFill(['blood_group' => 'O+'])->save());

        $this->as($w)->get($this->url($w, "id-cards/students/{$a->id}/pdf"))->assertOk();
        $html = $this->pdf->last()['html'];
        $this->assertStringNotContainsString('O+', $html);
        $this->assertStringNotContainsString('Guardian of Anika', $html);
        $this->assertStringContainsString('Property of the school', $html);
        $this->assertStringContainsString('12 School Road', $html);

        $teacher = $this->makeEmployee($w['branch'], ['name' => 'Salma Begum', 'blood_group' => 'A+']);
        $this->as($w)->get($this->url($w, 'id-cards?tab=staff'))->assertOk()->assertSee('Salma Begum');
        $this->as($w)->post($this->url($w, 'id-cards/print'), ['tab' => 'staff', 'ids' => [$teacher->id], 'layout' => 'card', 'sides' => 'both'])->assertOk();
        $card = $this->inBranch($w['branch'], fn () => IdCard::query()->where('holder_type', 'employee')->sole());
        $this->assertSame('IDE-000001', $card->card_no);
        $this->assertStringContainsString($teacher->employee_code, $this->pdf->last()['html']);
        $this->assertStringContainsString('STAFF ID CARD', $this->pdf->last()['html']);
    }

    public function test_lost_cards_are_invalid_on_the_public_page_and_replacing_voids_the_old_one(): void
    {
        $w = $this->world();
        $a = $w['students']['a'];
        $this->as($w)->get($this->url($w, "id-cards/students/{$a->id}/pdf"))->assertOk();
        $card = $this->inBranch($w['branch'], fn () => IdCard::query()->sole());

        $this->get($this->url($w, 'id/verify?code='.$card->verification_code))->assertOk()->assertSee(__('Valid ID card'))->assertSee('Anika Rahman');
        $this->get($this->url($w, 'id/verify?code=ZZZZZZZZZZ'))->assertOk()->assertSee(__('No ID card with this code was issued by this branch.'));
        $this->get($this->url($w, 'id/verify?code=bad-code!'))->assertSessionHasErrors('code');

        $this->as($w)->post($this->url($w, "id-cards/{$card->id}/revoke"), ['status' => 'lost', 'reason' => ''])->assertSessionHasErrors('reason');
        $this->as($w)->post($this->url($w, "id-cards/{$card->id}/revoke"), ['status' => 'stolen', 'reason' => 'x y z'])->assertSessionHasErrors('status');
        $this->as($w)->post($this->url($w, "id-cards/{$card->id}/revoke"), ['status' => 'lost', 'reason' => 'Lost on the bus'])->assertSessionHasNoErrors();
        $this->get($this->url($w, 'id/verify?code='.$card->verification_code))->assertOk()->assertSee(__('This card is no longer valid (:status).', ['status' => __('lost')]));
        $this->as($w)->post($this->url($w, "id-cards/{$card->id}/revoke"), ['status' => 'lost', 'reason' => 'again'])->assertSessionHasErrors('card');   // not active any more
        $this->as($w)->get($this->url($w, "id-cards/{$card->id}/pdf"))->assertStatus(422);

        // Printing again issues a new card because the old one is not active.
        $this->as($w)->get($this->url($w, "id-cards/students/{$a->id}/pdf"))->assertOk();
        $cards = $this->inBranch($w['branch'], fn () => IdCard::query()->orderBy('id')->get());
        $this->assertSame(['lost', 'active'], $cards->pluck('status')->all());

        // Replace the new one: the old one becomes "replaced", only one stays active.
        $this->as($w)->post($this->url($w, "id-cards/{$cards[1]->id}/reissue"), ['reason' => 'Damaged'])->assertSessionHasNoErrors();
        $cards = $this->inBranch($w['branch'], fn () => IdCard::query()->orderBy('id')->get());
        $this->assertSame(['lost', 'replaced', 'active'], $cards->pluck('status')->all());
        $this->assertSame('IDS-000003', $cards[2]->card_no);
        $this->get($this->url($w, 'id/verify?code='.$cards[1]->verification_code))->assertOk()->assertSee(__('This card was replaced by a newer card.'));
        $this->as($w)->get($this->url($w, 'id-cards/register'))->assertOk()->assertSee('IDS-000003')->assertSee('Damaged');
        $this->as($w)->get($this->url($w, 'id-cards/register?status=lost&holder=student&q=Anika'))->assertOk()->assertSee('IDS-000001')->assertDontSee('IDS-000003');
    }

    public function test_expired_cards_are_replaced_on_the_next_print_and_inactive_people_get_none(): void
    {
        $w = $this->world();
        $a = $w['students']['a'];
        $this->as($w)->get($this->url($w, "id-cards/students/{$a->id}/pdf"))->assertOk();
        $old = $this->inBranch($w['branch'], fn () => IdCard::query()->sole());
        $this->inBranch($w['branch'], fn () => $old->forceFill(['valid_until' => '2026-05-31'])->save());
        $this->assertTrue($this->inBranch($w['branch'], fn () => $old->fresh()->isExpired()));
        $this->get($this->url($w, 'id/verify?code='.$old->verification_code))->assertOk()->assertSee(__('This card has expired.'));

        $this->as($w)->get($this->url($w, "id-cards/students/{$a->id}/pdf"))->assertOk();
        $this->assertSame(['replaced', 'active'], $this->inBranch($w['branch'], fn () => IdCard::query()->orderBy('id')->pluck('status')->all()));

        $this->inBranch($w['branch'], fn () => $w['students']['b']->forceFill(['status' => 'withdrawn'])->save());
        $b = $w['students']['b'];
        $this->as($w)->post($this->url($w, 'id-cards/print'), ['tab' => 'students', 'ids' => [$b->id], 'layout' => 'sheet', 'sides' => 'both'])->assertStatus(422);
        $this->as($w)->get($this->url($w, "id-cards/students/{$b->id}/pdf"))->assertSessionHasErrors('card');
    }

    public function test_id_card_permissions_and_branch_isolation(): void
    {
        $w = $this->world();
        $a = $w['students']['a'];
        $teacher = $this->branchUser($w['branch'], 'teacher');
        $this->as($w, $teacher)->get($this->url($w, 'id-cards'))->assertForbidden();
        $this->as($w, $teacher)->post($this->url($w, 'id-cards/print'), ['tab' => 'students', 'ids' => [$a->id], 'layout' => 'sheet', 'sides' => 'both'])->assertForbidden();
        $this->as($w, $teacher)->get($this->url($w, 'id-cards/register'))->assertForbidden();

        $other = $this->makeBranch($w['branch']->institution);
        $otherAdmin = $this->branchUser($other, 'branch-admin');
        $this->actingOnBranch($otherAdmin, $other)->post($this->branchUrl($other, 'id-cards/print'), ['tab' => 'students', 'ids' => [$a->id], 'layout' => 'sheet', 'sides' => 'both'])->assertSessionHasErrors('ids.0');
        $this->actingOnBranch($otherAdmin, $other)->get($this->branchUrl($other, "id-cards/students/{$a->id}/pdf"))->assertNotFound();
        $this->assertSame(0, $this->inBranch($other, fn () => IdCard::query()->count()));

        $this->as($w)->post($this->url($w, 'id-cards/print'), ['tab' => 'students', 'ids' => [], 'layout' => 'sheet', 'sides' => 'both'])->assertSessionHasErrors('ids');
        $this->as($w)->post($this->url($w, 'id-cards/print'), ['tab' => 'students', 'ids' => [$a->id], 'layout' => 'poster', 'sides' => 'both'])->assertSessionHasErrors('layout');
    }

    // ------------------------------------------------------------------ exam documents

    protected function examWorld(): array
    {
        $w = $this->world();
        $f = $this->inBranch($w['branch'], function () use ($w) {
            $subject = Subject::query()->create(['name' => 'Bangla', 'code' => 'BAN', 'type' => 'theory', 'is_active' => true]);
            ClassSubject::query()->create(['school_class_id' => $w['setup']['class']->id, 'subject_id' => $subject->id, 'category' => 'compulsory', 'full_marks' => 100, 'pass_marks' => 33, 'sort_order' => 0]);
            $exam = Examination::query()->create([
                'academic_session_id' => $w['setup']['session']->id, 'grading_scale_id' => GradingScale::defaultForBranch()->id,
                'name' => 'Half-yearly 2026', 'type' => 'half_yearly', 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-15', 'status' => 'draft',
            ]);
            app(ExaminationService::class)->addClass($exam, $w['setup']['class']);
            app(ExaminationService::class)->changeStatus($exam, 'scheduled');

            return ['exam' => $exam, 'paper' => ExaminationSubject::query()->sole()];
        });

        return $w + $f;
    }

    public function test_admit_card_and_marksheet_pdfs(): void
    {
        $w = $this->examWorld();
        $sectionA = $w['setup']['sectionA'];
        $exam = $w['exam'];
        $a = $w['students']['a'];
        $b = $w['students']['b'];

        // marks → process
        $this->as($w)->post($this->url($w, "exams/{$exam->id}/marks/{$w['paper']->id}/{$sectionA->id}"), ['entries' => [$a->id => ['theory' => 85], $b->id => ['theory' => 20]]])->assertSessionHasNoErrors();
        $this->as($w)->post($this->url($w, "exams/{$exam->id}/results/process"))->assertSessionHasNoErrors();

        // admit cards
        $this->as($w)->get($this->url($w, "exams/{$exam->id}/admit-cards/pdf"))->assertSessionHasErrors('section_id');
        $this->as($w)->get($this->url($w, "exams/{$exam->id}/admit-cards/pdf?section_id={$sectionA->id}"))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $html = $this->pdf->last()['html'];
        $this->assertStringContainsString('Anika Rahman', $html);
        $this->assertStringContainsString('Babul Hasan', $html);
        $this->assertStringContainsString('Bangla', $html);
        $this->assertStringNotContainsString('Chandni', $html);                          // section B
        $this->assertStringContainsString('Prof. Rahman', $html);
        $this->as($w)->get($this->url($w, "exams/{$exam->id}/admit-cards/pdf?section_id=9999"))->assertNotFound();

        // mark sheets: one student, then the whole section
        $result = $this->inBranch($w['branch'], fn () => ExamResult::query()->where('student_id', $a->id)->sole());
        $this->as($w)->get($this->url($w, "exams/{$exam->id}/results/{$result->id}/pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $html = $this->pdf->last()['html'];
        $this->assertStringContainsString('Anika Rahman', $html);
        $this->assertStringContainsString('NOT PUBLISHED', $html);
        $this->assertStringNotContainsString('Babul', $html);

        $this->as($w)->get($this->url($w, "exams/{$exam->id}/results/marksheets/pdf?section_id={$sectionA->id}"))->assertOk();
        $html = $this->pdf->last()['html'];
        $this->assertStringContainsString('Anika Rahman', $html);
        $this->assertStringContainsString('Babul Hasan', $html);
        $this->assertSame(1, substr_count($html, '<pagebreak'));
        $this->as($w)->get($this->url($w, "exams/{$exam->id}/results/marksheets/pdf?section_id=9999"))->assertNotFound();

        $teacher = $this->branchUser($w['branch'], 'accountant');
        $this->as($w, $teacher)->get($this->url($w, "exams/{$exam->id}/results/{$result->id}/pdf"))->assertForbidden();
    }

    public function test_portal_marksheet_pdf_is_published_only_and_limited_to_own_or_children(): void
    {
        $w = $this->examWorld();
        $a = $w['students']['a'];
        $b = $w['students']['b'];
        $sectionA = $w['setup']['sectionA'];
        $exam = $w['exam'];

        $this->as($w)->post($this->url($w, "exams/{$exam->id}/marks/{$w['paper']->id}/{$sectionA->id}"), ['entries' => [$a->id => ['theory' => 85], $b->id => ['theory' => 70]]])->assertSessionHasNoErrors();
        $this->as($w)->post($this->url($w, "exams/{$exam->id}/results/process"))->assertSessionHasNoErrors();

        $this->as($w)->post($this->url($w, "students/{$a->id}/portal/student"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $guardian = $this->inBranch($w['branch'], fn () => $a->guardians()->first());
        $this->as($w)->post($this->url($w, "students/{$a->id}/portal/guardians/{$guardian->id}"), ['password' => 'secret123'])->assertSessionHasNoErrors();
        $studentUser = User::query()->where('username', strtolower($a->student_code))->sole();
        $parentUser = User::query()->where('phone', $guardian->phone)->sole();
        [$ra, $rb] = $this->inBranch($w['branch'], fn () => [ExamResult::query()->where('student_id', $a->id)->sole(), ExamResult::query()->where('student_id', $b->id)->sole()]);

        // Not published yet → nothing to download.
        $this->as($w, $studentUser)->get($this->url($w, "student/results/{$ra->id}/pdf"))->assertNotFound();
        $this->as($w, $parentUser)->get($this->url($w, "parent/results/{$ra->id}/pdf"))->assertNotFound();

        $this->as($w)->post($this->url($w, "exams/{$exam->id}/results/approve"))->assertSessionHasNoErrors();
        $this->as($w)->post($this->url($w, "exams/{$exam->id}/results/publish"))->assertSessionHasNoErrors();

        $response = $this->as($w, $studentUser)->get($this->url($w, "student/results/{$ra->id}/pdf"));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Anika Rahman', $this->pdf->last()['html']);
        $this->assertStringNotContainsString('Babul', $this->pdf->last()['html']);
        $this->as($w, $studentUser)->get($this->url($w, "student/results/{$rb->id}/pdf"))->assertNotFound();

        $this->as($w, $parentUser)->get($this->url($w, "parent/results/{$ra->id}/pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->as($w, $parentUser)->get($this->url($w, "parent/results/{$rb->id}/pdf"))->assertNotFound();
    }
}
