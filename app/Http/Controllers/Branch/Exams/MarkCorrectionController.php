<?php

namespace App\Http\Controllers\Branch\Exams;

use App\Http\Controllers\Concerns\SharedWithTeacherPortal;
use App\Http\Controllers\Controller;
use App\Models\Exams\ExaminationSubject;
use App\Models\Exams\MarkCorrection;
use App\Services\Exams\MarkCorrectionService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Mark correction requests after marks entry closed; review in the back-office. */
class MarkCorrectionController extends Controller
{
    use SharedWithTeacherPortal;

    public function __construct(protected MarkCorrectionService $corrections) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $reviewer = ! $this->inTeacherPortal($request) && $user->can('marks.approve-correction');
        abort_unless($reviewer || $user->can('marks.enter'), 403);

        $items = MarkCorrection::query()
            ->with(['mark.student:id,name,student_code', 'mark.paper.subject:id,name', 'mark.paper.schoolClass:id,name', 'mark.examination:id,name,status', 'requester:id,name', 'reviewer:id,name'])
            ->when(! $reviewer, fn ($q) => $q->where('requested_by', $user->id))
            ->when($request->input('status', $reviewer ? 'pending' : null), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate(25)->withQueryString();

        return view('branch.exams.corrections', [
            'items' => $items, 'reviewer' => $reviewer,
            'layout' => $this->layout($request), 'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'examination_subject_id' => ['required', 'integer', TenantRule::exists('examination_subjects')],
            'student_id' => ['required', 'integer', TenantRule::exists('students', 'id', true)],
            'marks' => ['nullable', 'array'],
            'marks.*' => ['nullable', 'numeric', 'min:0'],
            'absent' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        abort_unless($request->user()->can('marks.enter'), 403);
        $paper = ExaminationSubject::query()->with('examination')->findOrFail($data['examination_subject_id']);

        $correction = $this->corrections->request($paper, (int) $data['student_id'],
            ($data['marks'] ?? []) + ['absent' => $request->boolean('absent')], $data['reason'], $request->user());

        return back()->with('success', $correction->status === 'approved'
            ? __('Marks corrected. Results were processed again and need approval before publication.')
            : __('Correction request sent for approval.'));
    }

    public function approve(Request $request, MarkCorrection $correction): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->corrections->approve($correction, $request->user(), $data['note'] ?? null);

        return back()->with('success', __('Correction approved. Results were processed again and need approval before publication.'));
    }

    public function reject(Request $request, MarkCorrection $correction): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $this->corrections->reject($correction, $request->user(), $data['note']);

        return back()->with('success', __('Correction rejected.'));
    }
}
