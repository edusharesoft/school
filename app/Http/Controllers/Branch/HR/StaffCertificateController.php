<?php

namespace App\Http\Controllers\Branch\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Models\HR\StaffCertificate;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\HR\StaffCertificateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Experience and service certificates of staff: register, issue, PDF, revoke. */
class StaffCertificateController extends Controller
{
    public function __construct(protected StaffCertificateService $certificates, protected DocumentService $documents, protected PdfService $pdf) {}

    public function index(Request $request): View
    {
        $this->authorize('staff-certificates.view');

        $certificates = StaffCertificate::query()->with(['employee:id,name,employee_code', 'issuer:id,name'])
            ->when(in_array($request->query('type'), StaffCertificate::TYPES, true), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('serial_no', 'like', '%'.$request->query('q').'%')
                ->orWhereHas('employee', fn ($e) => $e->search($request->string('q')->toString()))))
            ->latest('id')->paginate(25)->withQueryString();

        return view('branch.hr.certificates.index', compact('certificates'));
    }

    public function store(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('staff-certificates.issue');
        $data = $request->validate([
            'type' => ['required', Rule::in(StaffCertificate::TYPES)],
            'conduct' => ['nullable', 'string', 'max:100'],
            'purpose' => ['nullable', 'string', 'max:200'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);
        $certificate = $this->certificates->issue($employee, $data['type'], $data, $request->user());

        return redirect()->route('branch.hr.certificates.index')->with('success', __('Certificate issued: :no', ['no' => $certificate->serial_no]));
    }

    public function pdf(StaffCertificate $certificate)
    {
        $this->authorize('staff-certificates.view');

        return $this->pdf->response($this->documents->staffCertificatePdf($certificate), 'certificate-'.$certificate->serial_no.'.pdf');
    }

    public function revoke(Request $request, StaffCertificate $certificate): RedirectResponse
    {
        $this->authorize('staff-certificates.issue');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->certificates->revoke($certificate, $data['reason']);

        return back()->with('success', __('Certificate revoked.'));
    }
}
