<?php

namespace App\Http\Controllers\Branch\Library;

use App\Http\Controllers\Controller;
use App\Models\Library\LibraryTransaction;
use App\Services\Library\LibraryMembers;
use App\Services\Library\LibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** The circulation desk: scan to issue, return, renew; open loans and overdue list. permission: library.issue */
class CirculationController extends Controller
{
    public function __construct(protected LibraryService $library) {}

    public function desk(Request $request): View
    {
        $this->authorize('library.issue');
        $member = null;
        $account = null;
        if ($parsed = LibraryMembers::parse($request->input('member'))) {
            $member = LibraryMembers::find(...$parsed);
            $account = $member ? $this->library->account($parsed[0], $parsed[1]) : null;
        }
        $recent = LibraryTransaction::query()->with('book:id,title,title_bn', 'copy:id,accession_no')->latest('id')->limit(8)->get();
        LibraryMembers::attach($recent);

        return view('branch.library.desk', ['member' => $member, 'account' => $account, 'memberKey' => $request->input('member'), 'recent' => $recent, 'library' => $this->library]);
    }

    public function members(Request $request): JsonResponse
    {
        $this->authorize('library.issue');

        return response()->json(LibraryMembers::search((string) $request->input('q', ''), 12)->map(fn ($m) => $m + ['key' => LibraryMembers::key($m['type'], $m['id'])])->values());
    }

    public function issue(Request $request): RedirectResponse
    {
        $this->authorize('library.issue');
        $data = $request->validate(['member' => ['required', 'string'], 'code' => ['required', 'string', 'max:60'], 'due_on' => ['nullable', 'date', 'after_or_equal:today'], 'note' => ['nullable', 'string', 'max:255']]);
        $parsed = LibraryMembers::parse($data['member']) ?? throw ValidationException::withMessages(['member' => __('Choose a member.')]);
        $copy = $this->library->findCopy($data['code']) ?? throw ValidationException::withMessages(['code' => __('No copy with this barcode or accession number.')]);

        $loan = $this->library->issue($copy, $parsed[0], $parsed[1], $request->user(), $data['due_on'] ?? null, $data['note'] ?? null);

        return redirect()->route('branch.library.desk', ['member' => $data['member']])
            ->with('success', __('":title" issued — due :date.', ['title' => $loan->book->displayTitle(), 'date' => $loan->due_on->format('d M Y')]));
    }

    public function return(Request $request): RedirectResponse
    {
        $this->authorize('library.issue');
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:60'], 'loan_id' => ['nullable', 'integer'], 'condition' => ['required', Rule::in(['good', 'damaged', 'lost'])],
            'damage_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'member' => ['nullable', 'string'],
        ]);
        if (filled($data['loan_id'] ?? null)) {
            $loan = LibraryTransaction::query()->open()->findOrFail($data['loan_id']);
        } else {
            $copy = $this->library->findCopy((string) ($data['code'] ?? '')) ?? throw ValidationException::withMessages(['code' => __('No copy with this barcode or accession number.')]);
            $loan = LibraryTransaction::query()->open()->where('library_book_copy_id', $copy->id)->first()
                ?? throw ValidationException::withMessages(['code' => __('This copy is not out on loan.')]);
        }

        $result = $this->library->returnCopy($loan, $data['condition'], $request->user(), (float) ($data['damage_fee'] ?? 0));
        $message = __('Returned: :title.', ['title' => $loan->book->displayTitle()]);
        if ($result['fines']->isNotEmpty()) {
            $message .= ' '.__('Fine charged: ৳:amount.', ['amount' => number_format((float) $result['fines']->sum('amount'), 2)]);
        }
        if ($result['promoted']) {
            $message .= ' '.__('Held for the next member in the queue.');
        }

        return back()->with($result['fines']->isNotEmpty() ? 'warning' : 'success', $message);
    }

    public function renew(LibraryTransaction $loan): RedirectResponse
    {
        $this->authorize('library.issue');
        try {
            $loan = $this->library->renew($loan);
        } catch (ValidationException $e) {
            return back()->with('warning', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Renewed — due :date.', ['date' => $loan->due_on->format('d M Y')]));
    }

    public function loans(Request $request): View
    {
        $this->authorize('library.view');
        $overdueOnly = $request->boolean('overdue');
        $loans = LibraryTransaction::query()->with('book:id,title,title_bn', 'copy:id,accession_no')
            ->when($overdueOnly, fn ($q) => $q->overdue(), fn ($q) => $q->when($request->input('status') !== 'all', fn ($w) => $w->open()))
            ->when(in_array($request->input('member_type'), ['student', 'employee'], true), fn ($q) => $q->where('member_type', $request->input('member_type')))
            ->when(trim((string) $request->input('q')), fn ($q, $t) => $q->whereHas('book', fn ($b) => $b->where('title', 'like', "%$t%")->orWhere('title_bn', 'like', "%$t%"))
                ->orWhereHas('copy', fn ($c) => $c->where('accession_no', 'like', "%$t%")))
            ->orderBy('due_on')->paginate(30)->withQueryString();
        LibraryMembers::attach($loans);

        return view('branch.library.loans', ['loans' => $loans, 'overdueOnly' => $overdueOnly, 'library' => $this->library]);
    }

    /** One member's whole library account. */
    public function member(string $type, int $id): View
    {
        $this->authorize('library.view');
        $member = LibraryMembers::find($type, $id) ?? abort(404);

        return view('branch.library.member', ['member' => $member, 'type' => $type, 'account' => $this->library->account($type, $id), 'library' => $this->library]);
    }
}
