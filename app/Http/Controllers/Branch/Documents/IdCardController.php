<?php

namespace App\Http\Controllers\Branch\Documents;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Documents\IdCard;
use App\Models\HR\Employee;
use App\Models\Students\Student;
use App\Services\Documents\DocumentService;
use App\Services\Documents\IdCardService;
use App\Services\Documents\PdfService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** ID cards of students and staff: pick people, print (A4 sheet or one card per page), keep the register, mark lost cards. */
class IdCardController extends Controller
{
    public const MAX_PER_PRINT = 300;

    public function __construct(protected IdCardService $cards, protected DocumentService $documents, protected PdfService $pdf) {}

    public function index(Request $request): View
    {
        $this->authorize('id-cards.view');
        $tab = $request->query('tab') === 'staff' ? 'staff' : 'students';
        $filters = $request->only(['session', 'class', 'section', 'q', 'type']);

        $data = ['tab' => $tab, 'filters' => $filters, 'limit' => self::MAX_PER_PRINT];
        if ($tab === 'students') {
            $data += [
                'rows' => $this->cards->studentRows($filters),
                'sessions' => AcademicSession::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_current']),
                'classes' => SchoolClass::query()->ordered()->get(['id', 'name', 'name_bn']),
                'sections' => Section::query()->with('schoolClass:id,name,name_bn,numeric_order')->where('is_active', true)->get()
                    ->sortBy(fn ($s) => sprintf('%05d-%s', $s->schoolClass?->numeric_order ?? 0, $s->name))->values(),
            ];
        } else {
            $data += ['rows' => $this->cards->employeeRows($filters)];
        }

        return view('branch.documents.id-cards.index', $data);
    }

    /** Print the ticked people: issues a card to anyone who has none, then one PDF. */
    public function print(Request $request)
    {
        $this->authorize('id-cards.issue');
        $data = $request->validate([
            'tab' => ['required', Rule::in(['students', 'staff'])],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PER_PRINT],
            'ids.*' => ['integer', $request->input('tab') === 'staff' ? TenantRule::exists('employees', 'id', true) : TenantRule::exists('students', 'id', true)],
            'layout' => ['required', Rule::in(['sheet', 'card'])],
            'sides' => ['required', Rule::in(['both', 'front'])],
            'session' => ['nullable', 'integer'],
        ]);
        $ids = array_map('intval', $data['ids']);

        $issued = collect();
        if ($data['tab'] === 'students') {
            // keep class / section / roll order
            $rows = $this->cards->studentRows(['session' => $data['session'] ?? null])->filter(fn ($r) => in_array($r['student']->id, $ids, true));
            abort_if($rows->isEmpty(), 422, __('None of the selected students is enrolled in the session.'));
            foreach ($rows as $row) {
                $card = $this->cards->issue($row['student'], $request->user());
                $issued->push([$card, $this->cards->cardData($card, $row['enrollment'])]);
            }
        } else {
            foreach (Employee::query()->whereIn('id', $ids)->where('status', 'active')->orderBy('name')->get() as $employee) {
                $card = $this->cards->issue($employee, $request->user());
                $issued->push([$card, $this->cards->cardData($card)]);
            }
            abort_if($issued->isEmpty(), 422, __('None of the selected staff members is active.'));
        }

        $pdf = $this->documents->idCardsPdf($issued->map(fn ($x) => $x[1]), $data['layout'], $data['sides']);
        $this->cards->markPrinted($issued->map(fn ($x) => $x[0]));

        return $this->pdf->response($pdf, 'id-cards-'.now()->format('Ymd-His').'.pdf');
    }

    /** One student's card (opens in a tab). */
    public function student(Request $request, Student $student)
    {
        $this->authorize('id-cards.issue');
        $this->authorize('view', $student);

        return $this->single($this->cards->issue($student, $request->user()), $request);
    }

    public function employee(Request $request, Employee $employee)
    {
        $this->authorize('id-cards.issue');
        $this->authorize('view', $employee);

        return $this->single($this->cards->issue($employee, $request->user()), $request);
    }

    /** Reprint a card from the register (the same number and validity). */
    public function reprint(Request $request, IdCard $card)
    {
        $this->authorize('id-cards.issue');
        abort_unless($card->isActive(), 422, __('Only an active card can be printed.'));

        return $this->single($card, $request);
    }

    public function register(Request $request): View
    {
        $this->authorize('id-cards.view');
        $cards = IdCard::query()->with(['student:id,name,student_code', 'employee:id,name,employee_code', 'issuer:id,name'])
            ->when($request->query('holder') === 'employee' || $request->query('holder') === 'student', fn ($q) => $q->where('holder_type', $request->query('holder')))
            ->when(in_array($request->query('status'), IdCard::STATUSES, true), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('card_no', 'like', '%'.$request->query('q').'%')
                ->orWhereHas('student', fn ($s) => $s->search($request->string('q')->toString()))
                ->orWhereHas('employee', fn ($e) => $e->search($request->string('q')->toString()))))
            ->latest('id')->paginate(30)->withQueryString();

        return view('branch.documents.id-cards.register', compact('cards'));
    }

    public function revoke(Request $request, IdCard $card): RedirectResponse
    {
        $this->authorize('id-cards.revoke');
        $data = $request->validate(['status' => ['required', Rule::in(['lost', 'revoked'])], 'reason' => ['required', 'string', 'min:3', 'max:255']]);
        $this->cards->revoke($card, $data['status'], $data['reason']);

        return back()->with('success', __('Card :no marked as :status.', ['no' => $card->card_no, 'status' => __($data['status'])]));
    }

    public function reissue(Request $request, IdCard $card): RedirectResponse
    {
        $this->authorize('id-cards.issue');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $new = $this->cards->reissue($card, $data['reason'], $request->user());

        return back()->with('success', __('New card :no issued — the old card is no longer valid.', ['no' => $new->card_no]));
    }

    protected function single(IdCard $card, Request $request)
    {
        $layout = $request->query('layout') === 'sheet' ? 'sheet' : 'card';
        $pdf = $this->documents->idCardsPdf(collect([$this->cards->cardData($card)]), $layout, $request->query('sides') === 'front' ? 'front' : 'both');
        $this->cards->markPrinted([$card]);

        return $this->pdf->response($pdf, 'id-card-'.$card->card_no.'.pdf');
    }
}
