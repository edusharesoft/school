<?php

namespace App\Http\Controllers\Branch\Exams;

use App\Http\Controllers\Controller;
use App\Models\Academic\Room;
use App\Models\Academic\Section;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Services\Documents\DocumentService;
use App\Services\Documents\PdfService;
use App\Services\Exams\ExamDocumentService;
use App\Services\Exams\ResultService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Results: processing & approval workflow, tabulation sheet, mark sheets, admit cards, seat plan. */
class ResultController extends Controller
{
    public function __construct(protected ResultService $results, protected ExamDocumentService $documents, protected DocumentService $pdfs, protected PdfService $pdf) {}

    public function index(Request $request, Examination $exam): View
    {
        $this->authorize('results.view');
        $exam->load(['session:id,name', 'gradingScale:id,name', 'approver:id,name']);

        $all = $exam->results()->with(['student:id,name,student_code', 'section:id,name', 'schoolClass:id,name,numeric_order'])->get();
        $classes = $all->pluck('schoolClass')->filter()->unique('id')->sortBy('numeric_order')->values();
        $classId = $request->integer('class_id') ?: $classes->first()?->id;
        $sectionId = $request->integer('section_id') ?: null;

        $list = $all->where('school_class_id', $classId)->when($sectionId, fn ($c) => $c->where('section_id', $sectionId))
            ->sortBy(fn ($r) => sprintf('%06d-%06d', $r->class_position ?? 999999, $r->roll_no ?? 0))->values();

        return view('branch.exams.results.index', [
            'exam' => $exam,
            'classes' => $classes,
            'classId' => $classId,
            'sectionId' => $sectionId,
            'sections' => $all->where('school_class_id', $classId)->pluck('section')->filter()->unique('id')->sortBy('name')->values(),
            'results' => $list,
            'summary' => $this->summary($all),
            'classSummary' => $this->summary($all->where('school_class_id', $classId)),
            'grades' => $all->where('school_class_id', $classId)->where('status', 'pass')->countBy('grade')->sortKeys(),
        ]);
    }

    public function process(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('results.process');
        $summary = $this->results->process($exam, $request->user());

        return redirect()->route('branch.exams.results.index', $exam)
            ->with('success', __('Results processed: :students students — :pass passed, :fail failed, :incomplete incomplete.', $summary));
    }

    public function approve(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('results.approve');
        $this->results->approve($exam, $request->user());

        return back()->with('success', __('Results approved. They can now be published.'));
    }

    public function publish(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('results.publish');
        $this->results->publish($exam, $request->user());

        return back()->with('success', __('Results published to students, parents and the public result page.'));
    }

    public function unpublish(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('results.publish');
        $this->results->unpublish($exam, $request->user());

        return back()->with('success', __('Results withdrawn from publication.'));
    }

    public function reopen(Request $request, Examination $exam): RedirectResponse
    {
        $this->authorize('results.process');
        $this->results->reopen($exam, $request->user());

        return redirect()->route('branch.exams.marks.index', ['exam_id' => $exam->id])->with('success', __('Marks entry reopened. Process the results again when marks are final.'));
    }

    /** Tabulation sheet of a class (optionally one section), printable landscape. */
    public function tabulation(Request $request, Examination $exam): View
    {
        $this->authorize('results.view');
        $data = $request->validate(['class_id' => ['required', 'integer'], 'section_id' => ['nullable', 'integer']]);

        $results = $exam->results()->with(['student:id,name,student_code', 'section:id,name', 'studyGroup:id,name', 'schoolClass:id,name'])
            ->where('school_class_id', $data['class_id'])->when($data['section_id'] ?? null, fn ($q, $s) => $q->where('section_id', $s))->get()
            ->sortBy(fn ($r) => sprintf('%s-%06d', $r->section?->name, $r->roll_no ?? 0))->values();
        abort_if($results->isEmpty(), 404);

        $columns = $results->flatMap(fn ($r) => collect($r->subjects)->map(fn ($s) => ['paper_id' => $s['paper_id'], 'subject' => $s['subject'], 'code' => $s['code'], 'full' => $s['full'], 'components' => array_keys($s['components'] ?? [])]))
            ->unique('paper_id')->values();

        return view('branch.exams.results.tabulation', ['exam' => $exam->load('session:id,name'), 'results' => $results, 'columns' => $columns,
            'section' => ($data['section_id'] ?? null) ? Section::query()->find($data['section_id']) : null]);
    }

    public function marksheet(Examination $exam, ExamResult $result): View
    {
        $this->authorize('results.view');
        abort_unless($result->examination_id === $exam->id, 404);

        return view('branch.exams.results.marksheets', $this->marksheetData($exam, collect([$result])) + ['layout' => 'layouts.base']);
    }

    /** All mark sheets of a section, one per page. */
    public function marksheets(Request $request, Examination $exam): View
    {
        $this->authorize('results.view');
        $data = $request->validate(['section_id' => ['required', 'integer']]);
        $results = $exam->results()->where('section_id', $data['section_id'])->orderBy('roll_no')->get();
        abort_if($results->isEmpty(), 404);

        return view('branch.exams.results.marksheets', $this->marksheetData($exam, $results) + ['layout' => 'layouts.base']);
    }

    public function admitCards(Request $request, Examination $exam): View
    {
        $this->authorize('examinations.view');
        abort_if($exam->status === 'draft', 404);
        $sections = Section::query()->with('schoolClass:id,name,numeric_order')->where('is_active', true)
            ->whereIn('school_class_id', $exam->subjects()->distinct()->pluck('school_class_id'))->get()
            ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->values();
        $section = $request->integer('section_id') ? $sections->firstWhere('id', $request->integer('section_id')) : null;

        return view('branch.exams.documents.admit-cards', [
            'exam' => $exam->load('session:id,name'),
            'sections' => $sections,
            'section' => $section,
            'cards' => $section ? $this->documents->admitCards($exam, $section) : collect(),
            'includeIneligible' => $request->boolean('include_ineligible'),
        ]);
    }


    /** Mark sheet of one student as a PDF. */
    public function marksheetPdf(Examination $exam, ExamResult $result)
    {
        $this->authorize('results.view');
        abort_unless($result->examination_id === $exam->id, 404);
        $pdf = $this->pdfs->marksheetsPdf(self::marksheetData($exam, collect([$result])));

        return $this->pdf->response($pdf, 'marksheet-'.($result->student?->student_code ?? $result->id).'.pdf');
    }

    /** All mark sheets of a section in one PDF, one per page. */
    public function marksheetsPdf(Request $request, Examination $exam)
    {
        $this->authorize('results.view');
        $data = $request->validate(['section_id' => ['required', 'integer']]);
        $results = $exam->results()->where('section_id', $data['section_id'])->orderBy('roll_no')->get();
        abort_if($results->isEmpty(), 404);

        return $this->pdf->response($this->pdfs->marksheetsPdf(self::marksheetData($exam, $results)), 'marksheets-'.$exam->id.'-section-'.$data['section_id'].'.pdf');
    }

    /** Admit cards of a section as a PDF (two per page). */
    public function admitCardsPdf(Request $request, Examination $exam)
    {
        $this->authorize('examinations.view');
        abort_if($exam->status === 'draft', 404);
        $data = $request->validate(['section_id' => ['required', 'integer'], 'include_ineligible' => ['nullable', 'boolean']]);
        $section = Section::query()->with('schoolClass:id,name,numeric_order')->where('is_active', true)
            ->whereIn('school_class_id', $exam->subjects()->distinct()->pluck('school_class_id'))->findOrFail($data['section_id']);
        $cards = $this->documents->admitCards($exam, $section)->filter(fn ($c) => $c['eligible'] || $request->boolean('include_ineligible'))->values();
        abort_if($cards->isEmpty(), 404);

        return $this->pdf->response($this->pdfs->admitCardsPdf($exam, $section, $cards), 'admit-cards-'.$exam->id.'-section-'.$section->id.'.pdf');
    }

    public function seatPlan(Request $request, Examination $exam): View
    {
        $this->authorize('examinations.view');
        abort_if($exam->status === 'draft', 404);
        $roomIds = array_map('intval', (array) $request->input('rooms', []));

        return view('branch.exams.documents.seat-plan', [
            'exam' => $exam->load('session:id,name'),
            'allRooms' => Room::query()->where('is_active', true)->where('capacity', '>', 0)->orderBy('code')->get(['id', 'name', 'code', 'capacity']),
            'selected' => $roomIds,
            'plan' => $this->documents->seatPlan($exam, $roomIds),
        ]);
    }

    /** Shared by the back-office, the portals and the public lookup. */
    public static function marksheetData(Examination $exam, Collection $results): array
    {
        $exam->loadMissing(['session:id,name', 'gradingScale' => fn ($q) => $q->withTrashed()]);
        $results = new EloquentCollection($results->all()); // callers may pass collect([$result])
        $results->loadMissing(['student:id,name,name_bn,student_code,date_of_birth', 'schoolClass:id,name', 'section:id,name', 'studyGroup:id,name']);

        // Highest total per paper across the class (printed on the mark sheet).
        $highest = [];
        foreach (ExamResult::query()->where('examination_id', $exam->id)->whereIn('school_class_id', $results->pluck('school_class_id')->unique())->get(['subjects']) as $r) {
            foreach ($r->subjects as $s) {
                if ($s['total'] !== null) {
                    $highest[$s['paper_id']] = max($highest[$s['paper_id']] ?? 0, (float) $s['total']);
                }
            }
        }

        return ['exam' => $exam, 'results' => $results, 'highest' => $highest, 'branch' => tenant()->branch()];
    }

    /** @return array{students:int, pass:int, fail:int, incomplete:int, pass_rate:?float, top_gpa:?float, avg_gpa:?float} */
    protected function summary(Collection $results): array
    {
        $ranked = $results->whereIn('status', ['pass', 'fail']);
        $pass = $results->where('status', 'pass');
        $max = $pass->max('gpa');

        return [
            'students' => $results->count(),
            'pass' => $pass->count(),
            'fail' => $results->where('status', 'fail')->count(),
            'incomplete' => $results->where('status', 'incomplete')->count(),
            'pass_rate' => $ranked->count() ? round($pass->count() / $ranked->count() * 100, 1) : null,
            'top_gpa' => $max,
            'avg_gpa' => $pass->count() ? round($pass->avg('gpa'), 2) : null,
        ];
    }
}
