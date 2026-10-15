<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Models\Finance\Account;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Models\Finance\FeeRefund;
use App\Models\Finance\StudentFeeAdjustment;
use App\Models\Students\Student;
use App\Services\Finance\FeePaymentService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** A student's fee account: dues, advance credit, invoices, receipts, refunds, ledger and fee adjustments. */
class StudentFeeController extends Controller
{
    public function __construct(protected FeePaymentService $payments) {}

    /** Find a student to collect fees from (ID, name, mobile, roll). */
    public function search(Request $request): View
    {
        abort_unless($request->user()->can('payments.collect') || $request->user()->can('payments.view') || $request->user()->can('invoices.view'), 403);
        $q = trim((string) $request->input('q'));

        $students = $q === '' ? collect() : Student::query()->search($q)->with('currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name')
            ->orderBy('name')->limit(30)->get();
        $dues = FeeInvoice::query()->open()->whereIn('student_id', $students->pluck('id'))->selectRaw('student_id, SUM(balance) as due')->groupBy('student_id')->toBase()->pluck('due', 'student_id');

        return view('branch.finance.students.search', ['q' => $q, 'students' => $students, 'dues' => $dues]);
    }

    public function show(Request $request, Student $student): View
    {
        abort_unless($request->user()->can('invoices.view') || $request->user()->can('payments.view') || $request->user()->can('payments.collect'), 403);
        $student->load(['currentEnrollment.schoolClass:id,name', 'currentEnrollment.section:id,name', 'currentEnrollment.session:id,name', 'guardians']);

        return view('branch.finance.students.show', [
            'student' => $student,
            'invoices' => FeeInvoice::query()->where('student_id', $student->id)->latest('issue_date')->latest('id')->get(),
            'payments' => FeePayment::query()->with('account:id,code,name', 'receiver:id,name')->where('student_id', $student->id)->latest('date')->latest('id')->get(),
            'refunds' => FeeRefund::query()->where('student_id', $student->id)->latest('date')->get(),
            'adjustments' => StudentFeeAdjustment::query()->with(['head:id,name', 'session:id,name', 'approver:id,name'])->where('student_id', $student->id)->latest('id')->get(),
            'outstanding' => $this->payments->outstanding($student),
            'credit' => $this->payments->credit($student),
            'ledger' => $request->user()->can('accounting.view') || $request->user()->can('invoices.view') ? $this->payments->ledger($student) : collect(),
            'heads' => FeeHead::query()->where('is_active', true)->ordered()->pluck('name', 'id'),
            'sessions' => AcademicSession::query()->latest('starts_on')->pluck('name', 'id'),
            'moneyAccounts' => Account::query()->money()->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
            'tab' => $request->input('tab', 'invoices'),
        ]);
    }

    public function storeAdjustment(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('fees.update');
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer', TenantRule::exists('academic_sessions')],
            'type' => ['required', Rule::in(StudentFeeAdjustment::TYPES)],
            'fee_head_id' => ['nullable', 'required_if:type,extra_fee', 'integer', TenantRule::exists('fee_heads')],
            'mode' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'months' => ['nullable', 'array'],
            'months.*' => ['integer', 'between:1,12'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        if ($data['type'] === 'extra_fee') {
            $data['mode'] = 'fixed';
            if (empty($data['months'])) {
                return back()->withErrors(['months' => __('Choose the months in which the extra fee is billed.')])->withInput();
            }
        }
        if ($data['mode'] === 'percent' && $data['value'] > 100) {
            return back()->withErrors(['value' => __('A percentage cannot exceed 100.')])->withInput();
        }

        $adjustment = new StudentFeeAdjustment($data + ['student_id' => $student->id, 'is_active' => true, 'months' => ! empty($data['months']) ? array_map('intval', $data['months']) : null]);
        $adjustment->forceFill(['approved_by' => $request->user()->id])->save();

        return redirect()->route('branch.finance.students.show', [$student, 'tab' => 'adjustments'])
            ->with('success', __('Saved. It applies to invoices generated from now on.'));
    }

    public function toggleAdjustment(Request $request, Student $student, StudentFeeAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('fees.update');
        abort_unless($adjustment->student_id === $student->id, 404);
        $adjustment->update(['is_active' => ! $adjustment->is_active]);

        return redirect()->route('branch.finance.students.show', [$student, 'tab' => 'adjustments'])->with('success', __('Updated.'));
    }
}
