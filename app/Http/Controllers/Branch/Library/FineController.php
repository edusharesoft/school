<?php

namespace App\Http\Controllers\Branch\Library;

use App\Http\Controllers\Controller;
use App\Models\Finance\FeePayment;
use App\Models\Library\LibraryFine;
use App\Services\Library\LibraryMembers;
use App\Services\Library\LibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Overdue / lost / damaged fines: collect (posts to the accounts), waive, print receipt. permission: library.issue */
class FineController extends Controller
{
    public function __construct(protected LibraryService $library) {}

    public function index(Request $request): View
    {
        $this->authorize('library.view');
        $status = in_array($request->input('status'), ['unpaid', 'paid', 'waived'], true) ? $request->input('status') : 'unpaid';
        $fines = LibraryFine::query()->with('transaction.book:id,title,title_bn')->where('status', $status)->latest('id')->paginate(30)->withQueryString();
        LibraryMembers::attach($fines);

        return view('branch.library.fines', [
            'fines' => $fines, 'status' => $status, 'methods' => FeePayment::METHODS,
            'totals' => LibraryFine::query()->selectRaw('status, sum(amount) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('library.issue');
        $data = $request->validate(['member' => ['required', 'string'], 'kind' => ['required', Rule::in(['damaged', 'lost', 'overdue'])], 'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'], 'note' => ['nullable', 'string', 'max:255']]);
        $parsed = LibraryMembers::parse($data['member']) ?? throw ValidationException::withMessages(['member' => __('Choose a member.')]);
        $this->library->charge($parsed[0], $parsed[1], $data['kind'], (float) $data['amount'], $data['note'] ?? null, $request->user());

        return back()->with('success', __('Fine recorded.'));
    }

    public function pay(Request $request, LibraryFine $fine): RedirectResponse
    {
        $this->authorize('library.issue');
        $data = $request->validate(['method' => ['required', Rule::in(FeePayment::METHODS)], 'paid_on' => ['nullable', 'date', 'before_or_equal:today']]);
        try {
            $fine = $this->library->payFine($fine, $data['method'], $request->user(), $data['paid_on'] ?? null);
        } catch (ValidationException $e) {
            return back()->with('warning', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Fine collected — receipt :no.', ['no' => $fine->receipt_no]));
    }

    public function waive(Request $request, LibraryFine $fine): RedirectResponse
    {
        $this->authorize('library.delete');   // waiving gives money away: managers only
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        try {
            $this->library->waiveFine($fine, $data['reason'], $request->user());
        } catch (ValidationException $e) {
            return back()->with('warning', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Fine waived.'));
    }

    public function receipt(LibraryFine $fine): View
    {
        $this->authorize('library.view');
        abort_unless($fine->status === 'paid', 404);
        LibraryMembers::attach([$fine]);

        return view('branch.library.receipt', ['fine' => $fine->load('transaction.book:id,title,title_bn')]);
    }
}
