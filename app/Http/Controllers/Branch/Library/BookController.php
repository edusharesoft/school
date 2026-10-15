<?php

namespace App\Http\Controllers\Branch\Library;

use App\Http\Controllers\Controller;
use App\Models\Library\LibraryBook;
use App\Models\Library\LibraryBookCopy;
use App\Models\Library\LibraryCategory;
use App\Models\Library\LibraryTransaction;
use App\Services\Library\LibraryMembers;
use App\Services\Library\LibraryService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Catalogue of titles and their physical copies. permission: library.* */
class BookController extends Controller
{
    public function __construct(protected LibraryService $library) {}

    public function index(Request $request): View
    {
        $this->authorize('library.view');
        $books = LibraryBook::query()->with('category:id,name,name_bn')
            ->withCount(['copies', 'copies as available_count' => fn ($q) => $q->where('status', 'available')])
            ->search($request->input('q'))
            ->when($request->integer('category_id'), fn ($q, $c) => $q->where('library_category_id', $c))
            ->when($request->input('availability') === 'available', fn ($q) => $q->whereHas('copies', fn ($c) => $c->where('status', 'available')))
            ->when($request->input('availability') === 'out', fn ($q) => $q->whereDoesntHave('copies', fn ($c) => $c->where('status', 'available'))->whereHas('copies'))
            ->orderBy('title')->paginate(25)->withQueryString();

        return view('branch.library.books.index', ['books' => $books, 'categories' => LibraryCategory::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->authorize('library.create');

        return view('branch.library.books.form', $this->formData(new LibraryBook(['language' => 'bn', 'is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('library.create');
        $data = $this->validated($request);
        $copies = (int) ($request->input('copies', 0));
        abort_if($copies < 0 || $copies > 200, 422);

        $book = new LibraryBook($data);
        $book->forceFill(['created_by' => $request->user()->id])->save();
        if ($copies > 0) {
            $this->library->addCopies($book, $copies, ['price' => $request->input('copy_price') ?: null, 'acquired_on' => now()->toDateString()]);
        }

        return redirect()->route('branch.library.books.show', $book)->with('success', __('Book saved.'));
    }

    public function show(LibraryBook $book): View
    {
        $this->authorize('library.view');
        $book->load('category');
        $copies = $book->copies()->orderBy('accession_no')->get();
        $holders = LibraryTransaction::query()->open()->where('library_book_id', $book->id)->get();
        $reservations = $book->reservations()->whereIn('status', ['waiting', 'ready'])->orderBy('id')->get();
        $history = LibraryTransaction::query()->where('library_book_id', $book->id)->latest('id')->limit(15)->with('copy:id,accession_no')->get();
        LibraryMembers::attach($holders);
        LibraryMembers::attach($reservations);
        LibraryMembers::attach($history);

        return view('branch.library.books.show', [
            'book' => $book, 'copies' => $copies, 'holders' => $holders->keyBy('library_book_copy_id'), 'reservations' => $reservations, 'history' => $history,
        ]);
    }

    public function edit(LibraryBook $book): View
    {
        $this->authorize('library.update');

        return view('branch.library.books.form', $this->formData($book));
    }

    public function update(Request $request, LibraryBook $book): RedirectResponse
    {
        $this->authorize('library.update');
        $book->fill($this->validated($request, $book))->save();

        return redirect()->route('branch.library.books.show', $book)->with('success', __('Book saved.'));
    }

    public function destroy(LibraryBook $book): RedirectResponse
    {
        $this->authorize('library.delete');
        if (LibraryTransaction::query()->where('library_book_id', $book->id)->exists() || $book->copies()->whereIn('status', ['issued', 'reserved'])->exists()) {
            return back()->with('warning', __('This title has lending history — mark it inactive instead of deleting it.'));
        }
        $book->copies()->delete();
        $book->delete();

        return redirect()->route('branch.library.books.index')->with('success', __('Book deleted.'));
    }

    public function addCopies(Request $request, LibraryBook $book): RedirectResponse
    {
        $this->authorize('library.update');
        $data = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:200'], 'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'acquired_on' => ['nullable', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->library->addCopies($book, (int) $data['count'], $data);

        return back()->with('success', __(':n copy(ies) added.', ['n' => $data['count']]));
    }

    public function copyStatus(Request $request, LibraryBookCopy $copy): RedirectResponse
    {
        $this->authorize('library.update');
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'lost', 'damaged', 'withdrawn'])]]);
        try {
            $this->library->setCopyStatus($copy, $data['status']);
        } catch (ValidationException $e) {
            return back()->with('warning', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Copy updated.'));
    }

    /** Printable sheet of barcode labels for a title (or every copy of the search result). */
    public function labels(Request $request, LibraryBook $book): View
    {
        $this->authorize('library.view');

        return view('branch.library.books.labels', ['book' => $book, 'copies' => $book->copies()->where('status', '!=', 'withdrawn')->orderBy('accession_no')->get()]);
    }

    protected function formData(LibraryBook $book): array
    {
        return ['book' => $book, 'categories' => LibraryCategory::query()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()];
    }

    protected function validated(Request $request, ?LibraryBook $book = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'], 'title_bn' => ['nullable', 'string', 'max:191'],
            'library_category_id' => ['nullable', 'integer', TenantRule::exists('library_categories')],
            'isbn' => ['nullable', 'string', 'max:20'], 'authors' => ['nullable', 'string', 'max:255'], 'publisher' => ['nullable', 'string', 'max:150'],
            'edition' => ['nullable', 'string', 'max:50'], 'language' => ['required', Rule::in(array_keys(config('library.languages')))],
            'published_year' => ['nullable', 'integer', 'min:1000', 'max:'.((int) date('Y') + 1)], 'pages' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'shelf' => ['nullable', 'string', 'max:50'], 'description' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'], 'copies' => ['nullable', 'integer', 'min:0', 'max:200'], 'copy_price' => ['nullable', 'numeric', 'min:0'],
        ]);
        if (filled($data['isbn'] ?? null)) {
            if (! LibraryBook::validIsbn($data['isbn'])) {
                throw ValidationException::withMessages(['isbn' => __('This is not a valid ISBN-10 or ISBN-13.')]);
            }
            $data['isbn'] = strtoupper(preg_replace('/[\s-]/', '', $data['isbn']));
        }
        $data['price'] = $data['price'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return collect($data)->except(['copies', 'copy_price'])->all();
    }
}
