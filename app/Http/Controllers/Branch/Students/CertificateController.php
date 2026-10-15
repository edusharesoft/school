<?php

namespace App\Http\Controllers\Branch\Students;

use App\Http\Controllers\Controller;
use App\Models\Students\Certificate;
use App\Models\Students\Student;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\Students\CertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Transfer / character / study certificates: register, issue, print, revoke. */
class CertificateController extends Controller
{
    public function __construct(protected CertificateService $certificates, protected DocumentService $documents, protected PdfService $pdf) {}

    public function index(Request $request): View
    {
        $this->authorize('certificates.view');

        $certificates = Certificate::query()->with(['student:id,name,student_code', 'issuer:id,name'])
            ->when(in_array($request->query('type'), Certificate::TYPES, true), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('serial_no', 'like', '%'.$request->query('q').'%')
                ->orWhereHas('student', fn ($s) => $s->search($request->string('q')->toString()))))
            ->latest('id')->paginate(25)->withQueryString();

        return view('branch.students.certificates.index', compact('certificates'));
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('certificates.issue');
        $this->authorize('view', $student);

        $data = $request->validate([
            'type' => ['required', Rule::in(Certificate::TYPES)],
            'conduct' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'leaving_date' => ['nullable', 'date', 'before_or_equal:today'],
            'mark_left' => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:150', 'required_if:type,achievement'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['type'] === 'transfer' && ! empty($data['mark_left'])) {
            $this->authorize('changeStatus', $student);
        }

        $certificate = $this->certificates->issue($student, $data['type'], $data, $request->user());

        return redirect()->route('branch.certificates.show', $certificate)->with('success', __('Certificate issued: :no', ['no' => $certificate->serial_no]));
    }

    /** Print-ready certificate (A4); the PDF with signature, seal and QR code is `pdf()`. */
    public function show(Certificate $certificate): View
    {
        $this->authorize('certificates.view');

        return view('branch.students.certificates.print', [
            'certificate' => $certificate->load(['student', 'issuer:id,name']),
            'branch' => tenant()->branch(),
            'verifyUrl' => route('certificates.verify', ['code' => $certificate->verification_code]),
        ]);
    }

    /** The certificate as a PDF (A4 landscape) with the branch's signature, seal and a QR code for verification. */
    public function pdf(Certificate $certificate)
    {
        $this->authorize('certificates.view');

        return $this->pdf->response($this->documents->certificatePdf($certificate), 'certificate-'.$certificate->serial_no.'.pdf');
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->authorize('certificates.issue');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->certificates->revoke($certificate, $data['reason']);

        return back()->with('success', __('Certificate revoked.'));
    }
}
