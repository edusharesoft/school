<?php

namespace App\Http\Controllers\Branch\Library;

use App\Http\Controllers\Controller;
use App\Models\Library\LibraryBook;
use App\Models\Library\LibraryBookCopy;
use App\Models\Library\LibraryFine;
use App\Models\Library\LibraryReservation;
use App\Models\Library\LibraryTransaction;
use App\Services\Library\LibraryMembers;
use Illuminate\View\View;

/** Library home: figures at a glance, due-today / overdue, most borrowed. */
class LibraryController extends Controller
{
    public function index(): View
    {
        $this->authorize('library.view');
        $today = now()->toDateString();
        $due = LibraryTransaction::query()->open()->whereDate('due_on', '<=', $today)->with('book:id,title,title_bn', 'copy:id,accession_no')->orderBy('due_on')->limit(10)->get();
        LibraryMembers::attach($due);

        return view('branch.library.index', [
            'stats' => [
                'titles' => LibraryBook::query()->count(),
                'copies' => LibraryBookCopy::query()->count(),
                'available' => LibraryBookCopy::query()->where('status', 'available')->count(),
                'out' => LibraryTransaction::query()->open()->count(),
                'overdue' => LibraryTransaction::query()->overdue()->count(),
                'reservations' => LibraryReservation::query()->whereIn('status', LibraryReservation::ACTIVE)->count(),
                'unpaid' => (float) LibraryFine::query()->where('status', 'unpaid')->sum('amount'),
            ],
            'due' => $due,
            'popular' => LibraryTransaction::query()->selectRaw('library_book_id, count(*) as loans')->where('issued_on', '>=', now()->subDays(90)->toDateString())
                ->groupBy('library_book_id')->orderByDesc('loans')->limit(5)->with('book:id,title,title_bn')->get(),
        ]);
    }
}
