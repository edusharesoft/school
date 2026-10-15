<?php

namespace App\Services\Documents;

use App\Models\Branch;
use App\Models\Documents\IdCard;
use App\Models\Exams\Examination;
use App\Models\HR\StaffCertificate;
use App\Models\Students\Certificate;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the printable PDFs (certificate, ID cards, admit cards, mark sheets) from the branch's document settings.
 * The HTML comes from resources/views/pdf; PdfService turns it into a file.
 */
class DocumentService
{
    public function __construct(
        protected PdfService $pdf,
        protected DocumentSettings $settings,
        protected IdCardService $cards,
        protected SettingsService $siteSettings,
    ) {}

    /** Letterhead, colours, signature and seal shared by every document. @return array<string,mixed> */
    public function branding(?Branch $branch = null): array
    {
        $branch ??= tenant()->branch();

        return [
            'branch' => $branch,
            'accent' => $this->settings->accent($branch),
            'logo' => $this->pdf->image($this->logoPath($branch), 300),
            'signature' => $this->pdf->image($this->settings->imagePath('signature_path'), 400),
            'seal' => $this->pdf->image($this->settings->imagePath('seal_path'), 400),
            'signatory' => $this->settings->signatoryName($branch),
            'signatory_title' => $this->settings->signatoryTitle(),
            'footer' => (string) $this->settings->get('certificate_footer'),
            'style' => in_array($this->settings->get('certificate_style'), DocumentSettings::CERT_STYLES, true) ? $this->settings->get('certificate_style') : 'classic',
        ];
    }

    public function certificatePdf(Certificate $certificate): string
    {
        $certificate->loadMissing('student');
        $html = view('pdf.certificate', $this->branding() + [
            'certificate' => $certificate,
            'verifyUrl' => route('certificates.verify', ['code' => $certificate->verification_code]),
        ])->render();

        return $this->pdf->render($html, [
            'format' => 'A4', 'orientation' => 'L', 'margins' => [8, 8, 8, 8], 'title' => $certificate->title().' '.$certificate->serial_no,
            'watermark' => $certificate->isRevoked() ? __('REVOKED') : null,
        ]);
    }

    /** Experience / service certificate of a staff member: same page as the student certificates, with its own wording. */
    public function staffCertificatePdf(StaffCertificate $certificate): string
    {
        $certificate->loadMissing('employee');
        $html = view('pdf.certificate', $this->branding() + [
            'certificate' => $certificate, 'text' => 'branch.hr.certificates._text',
            'verifyUrl' => route('certificates.verify', ['code' => $certificate->verification_code]),
        ])->render();

        return $this->pdf->render($html, [
            'format' => 'A4', 'orientation' => 'L', 'margins' => [8, 8, 8, 8], 'title' => $certificate->title().' '.$certificate->serial_no,
            'watermark' => $certificate->isRevoked() ? __('REVOKED') : null,
        ]);
    }

    /**
     * @param  Collection<int,array<string,mixed>>  $rows  IdCardService::cardData rows
     * @param  'card'|'sheet'  $layout
     * @param  'both'|'front'  $sides
     */
    public function idCardsPdf(Collection $rows, string $layout = 'sheet', string $sides = 'both'): string
    {
        $layout = $layout === 'card' ? 'card' : 'sheet';
        $sides = $sides === 'front' ? 'front' : 'both';
        $b = $this->branding();
        $cards = $rows->map(function (array $c) {
            $c['photo'] = $this->pdf->image($this->photoPath($c['photo_path'] ?? null), 320, 22 / 27);

            return $c;
        })->values()->all();
        $html = view('pdf.id-cards', [
            'cards' => $cards, 'layout' => $layout, 'sides' => $sides, 'branch' => $b['branch'], 'color' => $this->settings->cardColor($b['branch']),
            'logo' => $b['logo'], 'signature' => $b['signature'], 'signatory' => $b['signatory'], 'signatory_title' => $b['signatory_title'],
            'back_text' => (string) $this->settings->get('card_back_text'),
            'emergency' => (string) ($this->settings->get('card_emergency_phone') ?: $b['branch']->phone),
            'show_blood' => (bool) $this->settings->get('card_show_blood_group'), 'show_phone' => (bool) $this->settings->get('card_show_guardian_phone'),
        ])->render();

        $card = config('pdf.card');

        return $this->pdf->render($html, $layout === 'card'
            ? ['format' => [$card['width'], $card['height']], 'margins' => [0, 0, 0, 0], 'title' => __('ID cards')]
            : ['format' => 'A4', 'margins' => [8, 6, 8, 6], 'title' => __('ID cards')]);
    }

    /** @param  Collection<int,array<string,mixed>>  $cards  ExamDocumentService::admitCards rows */
    public function admitCardsPdf(Examination $exam, $section, Collection $cards): string
    {
        $b = $this->branding();
        $rows = $cards->map(function (array $card) {
            $card['photo'] = $this->pdf->image($this->photoPath($card['enrollment']->student?->photo_path), 240, 24 / 29);

            return $card;
        })->values()->all();
        $html = view('pdf.admit-cards', $b + ['exam' => $exam->loadMissing('session:id,name'), 'section' => $section, 'cards' => $rows])->render();

        return $this->pdf->render($html, ['format' => 'A4', 'margins' => [8, 10, 8, 10], 'title' => __('Admit card').' — '.$exam->name]);
    }

    /** @param  array<string,mixed>  $data  ResultController::marksheetData */
    public function marksheetsPdf(array $data): string
    {
        $html = view('pdf.marksheets', $this->branding() + $data)->render();

        return $this->pdf->render($html, ['format' => 'A4', 'margins' => [10, 12, 10, 12], 'title' => __('Mark sheet').' — '.$data['exam']->name]);
    }

    /** Absolute path of a student's / employee's photo on the private disk (null when none). */
    public function photoPath(?string $relative): ?string
    {
        if (! $relative || ! Storage::disk('local')->exists($relative)) {
            return null;
        }

        return Storage::disk('local')->path($relative);
    }

    protected function logoPath(?Branch $branch): ?string
    {
        foreach ([$branch?->logo_path, $branch?->institution?->logo_path, $this->siteSettings->get('site', 'site_logo')] as $path) {
            if ($path && Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->path($path);
            }
        }

        return null;
    }
}
