<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Branch\Exams\ResultController;
use App\Http\Controllers\Controller;
use App\Models\Academic\Section;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Students\Student;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\Exams\ExamDocumentService;
use App\Services\Finance\FeePaymentService;
use App\Services\Portal\PortalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Student portal: profile, attendance, published results & mark sheets, admit cards. */
class StudentPortalController extends Controller
{
    public function __construct(protected PortalService $portal) {}

    public function index(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());

        return view('portal.home', [
            'portal' => 'student',
            'user' => $request->user()->load('roles'),
            'student' => $student,
            'attendance' => $student ? $this->portal->attendance($student, $student->currentEnrollment?->session) : null,
            'results' => $student ? $this->portal->publishedResults($student, 5) : collect(),
            'upcoming' => $student ? $this->portal->upcomingExams($student) : collect(),
        ]);
    }

    public function results(Request $request): View
    {
        $student = $this->student($request);

        return view('portal.results', ['student' => $student, 'results' => $this->portal->publishedResults($student), 'routePrefix' => 'portal.student.']);
    }

    public function marksheet(Request $request, ExamResult $result): View
    {
        $student = $this->student($request);
        abort_unless($result->student_id === $student->id && $result->examination?->isPublished(), 404);

        return view('branch.exams.results.marksheets', ResultController::marksheetData($result->examination, collect([$result])) + ['back' => route('portal.student.results')]);
    }

    /** The student's own published mark sheet as a PDF download. */
    public function marksheetPdf(Request $request, ExamResult $result, DocumentService $documents, PdfService $pdf)
    {
        $student = $this->student($request);
        abort_unless($result->student_id === $student->id && $result->examination?->isPublished(), 404);

        return $pdf->response($documents->marksheetsPdf(ResultController::marksheetData($result->examination, collect([$result]))), 'marksheet-'.($student->student_code ?? $result->id).'.pdf', true);
    }

    public function admitCard(Request $request, Examination $exam, ExamDocumentService $documents): View
    {
        $student = $this->student($request);
        $enrollment = $student->currentEnrollment;
        abort_unless($enrollment && $enrollment->section_id && in_array($exam->status, ['scheduled', 'marks_entry'], true)
            && $exam->academic_session_id === $enrollment->academic_session_id, 404);

        $card = $documents->admitCards($exam, Section::query()->findOrFail($enrollment->section_id))->first(fn ($c) => $c['enrollment']->student_id === $student->id);
        abort_unless($card, 404);
        abort_unless($card['eligible'], 403, __('Your attendance (:pct%) is below the :min% needed for this exam. Please contact the school office.', ['pct' => $card['attendance'], 'min' => $exam->min_attendance]));

        return view('branch.exams.documents.admit-card-single', [
            'exam' => $exam->load('session:id,name'), 'card' => $card, 'section' => Section::query()->with('schoolClass:id,name')->find($enrollment->section_id),
            'branch' => tenant()->branch(), 'back' => route('portal.student'),
        ]);
    }

    public function fees(Request $request): View
    {
        $student = $this->student($request);

        return view('portal.fees', ['student' => $student, 'routePrefix' => 'portal.student.'] + $this->portal->fees($student));
    }

    public function invoice(Request $request, FeeInvoice $invoice): View
    {
        abort_unless($invoice->student_id === $this->student($request)->id && $invoice->status !== 'cancelled', 404);
        $invoice->load(['items', 'student', 'schoolClass:id,name', 'section:id,name']);

        return view('branch.finance.invoices.print', ['invoice' => $invoice, 'branch' => tenant()->branch(), 'back' => route('portal.student.fees')]);
    }

    public function receipt(Request $request, FeePayment $payment, FeePaymentService $payments): View
    {
        $student = $this->student($request);
        abort_unless($payment->student_id === $student->id && ! $payment->isReversed(), 404);

        return view('branch.finance.payments.receipt', ['payment' => $payment->load(['student', 'allocations.invoice', 'account', 'receiver:id,name']),
            'branch' => tenant()->branch(), 'balance' => $payments->outstanding($student), 'credit' => $payments->credit($student), 'note' => '', 'back' => route('portal.student.fees')]);
    }

    protected function student(Request $request): Student
    {
        $student = $this->portal->studentFor($request->user());
        abort_unless($student, 404, __('Your login is not linked to a student record. Please contact the school office.'));

        return $student;
    }
}
