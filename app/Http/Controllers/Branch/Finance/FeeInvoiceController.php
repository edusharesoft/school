<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeInvoice;
use App\Models\Students\Student;
use App\Services\Finance\FeeInvoiceService;
use App\Services\Finance\FinanceSettings;
use App\Support\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Invoice list, monthly generation, one-off invoices, printing, waivers, cancellation and late fees. */
class FeeInvoiceController extends Controller
{
    public function __construct(protected FeeInvoiceService $invoices) {}

    public function index(Request $request): View
    {
        $this->authorize('invoices.view');
        $query = FeeInvoice::query()->with(['student:id,name,student_code', 'schoolClass:id,name', 'section:id,name'])
            ->when($request->input('status'), fn ($q, $s) => $s === 'overdue' ? $q->overdue() : $q->where('status', $s))
            ->when($request->input('period'), fn ($q, $p) => $q->where('period', $p))
            ->when($request->integer('class_id'), fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($request->integer('section_id'), fn ($q, $id) => $q->where('section_id', $id))
            ->when(trim((string) $request->input('q')), fn ($q, $term) => $q->where(fn ($w) => $w->where('invoice_no', 'like', "%$term%")
                ->orWhereHas('student', fn ($s) => $s->search($term))));

        return view('branch.finance.invoices.index', [
            'invoices' => (clone $query)->latest('issue_date')->latest('id')->paginate(30)->withQueryString(),
            'totals' => (clone $query)->where('status', '!=', 'cancelled')->selectRaw('COALESCE(SUM(total),0) as total, COALESCE(SUM(paid),0) as paid, COALESCE(SUM(balance),0) as balance')->toBase()->first(),
            'classes' => SchoolClass::query()->active()->ordered()->pluck('name', 'id'),
            'sections' => $request->integer('class_id') ? Section::query()->where('school_class_id', $request->integer('class_id'))->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function show(Request $request, FeeInvoice $invoice): View
    {
        abort_unless($request->user()->can('invoices.view') || $request->user()->can('payments.collect'), 403);
        $invoice->load(['items', 'student.guardians', 'schoolClass:id,name', 'section:id,name', 'session:id,name', 'allocations.payment', 'creator:id,name']);

        return view($request->boolean('print') ? 'branch.finance.invoices.print' : 'branch.finance.invoices.show', [
            'invoice' => $invoice,
            'branch' => tenant()->branch(),
            'entries' => app(\App\Services\Finance\LedgerService::class)->entriesFor($invoice),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('invoices.create');
        $student = Student::query()->with('currentEnrollment.schoolClass:id,name')->findOrFail($request->integer('student_id'));

        return view('branch.finance.invoices.create', [
            'student' => $student,
            'heads' => FeeHead::query()->where('is_active', true)->ordered()->get(['id', 'name', 'category']),
            'dueDay' => (int) app(FinanceSettings::class)->get('due_day'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('invoices.create');
        $data = $request->validate([
            'student_id' => ['required', 'integer', TenantRule::exists('students', 'id', true)],
            'title' => ['required', 'string', 'max:150'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.fee_head_id' => ['nullable', 'integer', TenantRule::exists('fee_heads')],
            'items.*.description' => ['nullable', 'string', 'max:150'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);
        $student = Student::query()->findOrFail($data['student_id']);
        $heads = FeeHead::query()->pluck('name', 'id');
        $items = collect($data['items'])->filter(fn ($i) => (float) ($i['amount'] ?? 0) > 0)->map(fn ($i) => [
            'fee_head_id' => $i['fee_head_id'] ?? null,
            'description' => trim((string) ($i['description'] ?? '')) ?: ($heads[$i['fee_head_id'] ?? 0] ?? __('Fee')),
            'amount' => (float) $i['amount'],
            'discount' => (float) ($i['discount'] ?? 0),
            'discount_note' => (float) ($i['discount'] ?? 0) > 0 ? __('Discount') : null,
        ])->values()->all();

        $invoice = $this->invoices->create($student, null, $items, $data['title'], $data['issue_date'], $data['due_date'], null, null, $request->user(), $data['notes'] ?? null);

        return redirect()->route('branch.finance.invoices.show', $invoice)->with('success', __('Invoice :no created.', ['no' => $invoice->invoice_no]));
    }

    public function generateForm(): View
    {
        $this->authorize('invoices.generate');

        return view('branch.finance.invoices.generate', [
            'classes' => SchoolClass::query()->active()->ordered()->pluck('name', 'id'),
            'period' => CarbonImmutable::today()->format('Y-m'),
            'recent' => FeeInvoice::query()->whereNotNull('period')->selectRaw('period, COUNT(*) as n, SUM(total) as total')->groupBy('period')->orderByDesc('period')->limit(12)->toBase()->get(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->authorize('invoices.generate');
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'school_class_id' => ['nullable', 'integer', TenantRule::exists('school_classes', 'id', true)],
        ]);
        $stats = $this->invoices->generateForPeriod($data['period'], $data['school_class_id'] ?? null, $request->user());

        return back()->with('success', __(':created invoice(s) created for :period; :skipped already existed, :empty student(s) had no fee that month.', $stats));
    }

    public function cancel(Request $request, FeeInvoice $invoice): RedirectResponse
    {
        $this->authorize('invoices.delete');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->invoices->cancel($invoice, $data['reason'], $request->user());

        return back()->with('success', __('Invoice cancelled. Any amount already paid is kept as advance credit.'));
    }

    public function waive(Request $request, FeeInvoice $invoice): RedirectResponse
    {
        $this->authorize('invoices.update');
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', 'string', 'min:5', 'max:200']]);
        $this->invoices->waive($invoice, (float) $data['amount'], $data['reason'], $request->user());

        return back()->with('success', __('Waiver recorded.'));
    }

    public function lateFees(Request $request): RedirectResponse
    {
        $this->authorize('invoices.generate');
        $count = $this->invoices->applyLateFees();

        return back()->with('success', __('Late fee charged on :count overdue invoice(s).', ['count' => $count]));
    }
}
