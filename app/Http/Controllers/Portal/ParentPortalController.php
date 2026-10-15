<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Branch\Exams\ResultController;
use App\Http\Controllers\Controller;
use App\Models\Exams\ExamResult;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\Finance\FeePaymentService;
use App\Services\Portal\PortalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Parent portal: every linked child with class, attendance and latest results; published mark sheets. */
class ParentPortalController extends Controller
{
    public function __construct(protected PortalService $portal) {}

    public function index(Request $request): View
    {
        $guardian = $this->portal->guardianFor($request->user());
        $children = $guardian ? $this->portal->children($guardian)->map(fn (Student $s) => [
            'student' => $s,
            'attendance' => $this->portal->attendance($s, $s->currentEnrollment?->session),
            'result' => $this->portal->publishedResults($s, 1)->first(),
            'due' => $this->portal->fees($s)['due'],
        ]) : collect();

        return view('portal.home', ['portal' => 'parent', 'user' => $request->user()->load('roles'), 'guardian' => $guardian, 'children' => $children]);
    }

    public function results(Request $request, Student $student): View
    {
        $this->assertChild($request, $student);

        return view('portal.results', ['student' => $student, 'results' => $this->portal->publishedResults($student), 'routePrefix' => 'portal.parent.']);
    }

    public function marksheet(Request $request, ExamResult $result): View
    {
        $this->assertChild($request, $result->student);
        abort_unless($result->examination?->isPublished(), 404);

        return view('branch.exams.results.marksheets', ResultController::marksheetData($result->examination, collect([$result]))
            + ['back' => route('portal.parent.results', $result->student_id)]);
    }

    /** A child's published mark sheet as a PDF download. */
    public function marksheetPdf(Request $request, ExamResult $result, DocumentService $documents, PdfService $pdf)
    {
        $this->assertChild($request, $result->student);
        abort_unless($result->examination?->isPublished(), 404);

        return $pdf->response($documents->marksheetsPdf(ResultController::marksheetData($result->examination, collect([$result]))), 'marksheet-'.($result->student?->student_code ?? $result->id).'.pdf', true);
    }

    public function fees(Request $request, Student $student): View
    {
        $this->assertChild($request, $student);

        return view('portal.fees', ['student' => $student, 'routePrefix' => 'portal.parent.'] + $this->portal->fees($student));
    }

    public function invoice(Request $request, FeeInvoice $invoice): View
    {
        $this->assertChild($request, $invoice->student);
        abort_if($invoice->status === 'cancelled', 404);
        $invoice->load(['items', 'student', 'schoolClass:id,name', 'section:id,name']);

        return view('branch.finance.invoices.print', ['invoice' => $invoice, 'branch' => tenant()->branch(), 'back' => route('portal.parent.fees', $invoice->student_id)]);
    }

    public function receipt(Request $request, FeePayment $payment, FeePaymentService $payments): View
    {
        $this->assertChild($request, $payment->student);
        abort_if($payment->isReversed(), 404);

        return view('branch.finance.payments.receipt', ['payment' => $payment->load(['student', 'allocations.invoice', 'account', 'receiver:id,name']),
            'branch' => tenant()->branch(), 'balance' => $payments->outstanding($payment->student), 'credit' => $payments->credit($payment->student), 'note' => '',
            'back' => route('portal.parent.fees', $payment->student_id)]);
    }

    protected function assertChild(Request $request, ?Student $student): Guardian
    {
        $guardian = $this->portal->guardianFor($request->user());
        abort_unless($guardian && $student && $this->portal->isChildOf($guardian, $student), 404);

        return $guardian;
    }
}
