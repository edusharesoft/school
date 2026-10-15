<?php

namespace App\Http\Controllers\Branch\Students;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\StudyGroup;
use App\Models\Students\Student;
use App\Models\Students\StudentTransfer;
use App\Services\Students\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Transfers between branches: outgoing requests from this branch and incoming requests to it. */
class TransferController extends Controller
{
    public function __construct(protected TransferService $transfers) {}

    public function index(): View
    {
        $this->authorize('students.transfer');
        $branchId = tenant()->branchId();

        $transfers = StudentTransfer::query()->involvingBranch($branchId)
            ->with(['fromBranch:id,name,code', 'toBranch:id,name,code', 'sourceStudent:id,name,student_code', 'newStudent:id,student_code', 'requester:id,name', 'decider:id,name'])
            ->latest()->paginate(25);

        return view('branch.students.transfers.index', [
            'transfers' => $transfers,
            'branchId' => $branchId,
            'sessions' => AcademicSession::query()->where('status', 'open')->latest('starts_on')->get(['id', 'name', 'is_current']),
            'classes' => SchoolClass::query()->active()->ordered()->with(['sections' => fn ($q) => $q->where('is_active', true)])->get(),
            'groups' => StudyGroup::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('students.transfer');
        $this->authorize('update', $student);

        $data = $request->validate([
            'to_branch_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->transfers->request($student, (int) $data['to_branch_id'], $data['reason'], $request->user());

        return redirect()->route('branch.transfers.index')->with('success', __('Transfer request sent to the other branch.'));
    }

    public function accept(Request $request, StudentTransfer $transfer): RedirectResponse
    {
        $this->authorize('students.transfer');
        $this->authorize('students.create');
        abort_unless($transfer->to_branch_id === tenant()->branchId(), 404);

        $data = $request->validate([
            'academic_session_id' => ['required', 'integer'],
            'school_class_id' => ['required', 'integer'],
            'section_id' => ['required', 'integer'],
            'study_group_id' => ['nullable', 'integer'],
        ]);

        $student = $this->transfers->accept($transfer, $data, $request->user());

        return redirect()->route('branch.students.show', $student)->with('success', __('Transfer accepted. New student ID: :code', ['code' => $student->student_code]));
    }

    public function reject(Request $request, StudentTransfer $transfer): RedirectResponse
    {
        $this->authorize('students.transfer');
        abort_unless($transfer->to_branch_id === tenant()->branchId(), 404);

        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $this->transfers->reject($transfer, $data['note'], $request->user());

        return back()->with('success', __('Transfer request rejected.'));
    }

    public function cancel(Request $request, StudentTransfer $transfer): RedirectResponse
    {
        $this->authorize('students.transfer');
        abort_unless($transfer->from_branch_id === tenant()->branchId(), 404);

        $this->transfers->cancel($transfer, $request->user());

        return back()->with('success', __('Transfer request cancelled.'));
    }
}
