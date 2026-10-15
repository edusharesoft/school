<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Services\Finance\ChartOfAccounts;
use App\Services\Finance\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Day book (all vouchers) and manual vouchers: journal, contra (cash ↔ bank), receipt and payment. */
class JournalController extends Controller
{
    public const MANUAL_TYPES = ['journal', 'contra', 'receipt', 'payment'];

    public function __construct(protected LedgerService $ledger) {}

    public function index(Request $request): View
    {
        $this->authorize('accounting.view');
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        return view('branch.finance.journal.index', [
            'entries' => JournalEntry::query()->with(['lines.account:id,code,name', 'creator:id,name'])
                ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
                ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
                ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('entry_no', 'like', "%$t%")->orWhere('narration', 'like', "%$t%")))
                ->latest('date')->latest('id')->paginate(30)->withQueryString(),
            'from' => $from, 'to' => $to,
        ]);
    }

    public function create(): View
    {
        $this->authorize('accounting.post');
        app(ChartOfAccounts::class)->ensure();

        return view('branch.finance.journal.create', [
            'accounts' => Account::query()->postable()->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('accounting.post');
        $data = $request->validate([
            'type' => ['required', Rule::in(self::MANUAL_TYPES)],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'narration' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:30'],
            'lines.*.account_id' => ['nullable', 'integer'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);
        $lines = collect($data['lines'])->filter(fn ($l) => ! empty($l['account_id']) && ((float) ($l['debit'] ?? 0) > 0 || (float) ($l['credit'] ?? 0) > 0))->values()->all();

        // Contra vouchers move money between cash, bank and mobile accounts only.
        if ($data['type'] === 'contra') {
            $money = Account::query()->money()->pluck('id')->flip();
            if (collect($lines)->contains(fn ($l) => ! $money->has((int) $l['account_id']))) {
                return back()->withErrors(['lines' => __('A contra voucher may only use cash, bank and mobile-banking accounts.')])->withInput();
            }
        }

        $entry = $this->ledger->post($data['type'], $data['date'], $data['narration'], $lines, null, $request->user());

        return redirect()->route('branch.finance.journal.show', $entry)->with('success', __('Voucher :no posted.', ['no' => $entry->entry_no]));
    }

    public function show(JournalEntry $entry): View
    {
        $this->authorize('accounting.view');

        return view('branch.finance.journal.show', ['entry' => $entry->load(['lines.account', 'lines.student:id,name,student_code', 'creator:id,name', 'reversalOf', 'reversedBy']),
            'branch' => tenant()->branch()]);
    }

    public function reverse(Request $request, JournalEntry $entry): RedirectResponse
    {
        $this->authorize('accounting.reverse');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        if ($entry->source_type) {
            return back()->withErrors(['reason' => __('This voucher belongs to a fee, expense or payroll document — reverse it from that document.')]);
        }
        $reversal = $this->ledger->reverse($entry, $data['reason'], $request->user());

        return redirect()->route('branch.finance.journal.show', $reversal)->with('success', __('Voucher reversed.'));
    }
}
