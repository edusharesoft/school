<?php

namespace App\Http\Controllers\Branch\Library;

use App\Http\Controllers\Controller;
use App\Models\Library\LibraryBook;
use App\Models\Library\LibraryReservation;
use App\Services\Library\LibraryMembers;
use App\Services\Library\LibraryService;
use App\Support\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Reservation queue for titles whose copies are all out. permission: library.* */
class ReservationController extends Controller
{
    public function __construct(protected LibraryService $library) {}

    public function index(Request $request): View
    {
        $this->authorize('library.view');
        $reservations = LibraryReservation::query()->with('book:id,title,title_bn', 'copy:id,accession_no')
            ->when($request->input('status') !== 'all', fn ($q) => $q->whereIn('status', LibraryReservation::ACTIVE))
            ->orderBy('id')->paginate(30)->withQueryString();
        LibraryMembers::attach($reservations);

        return view('branch.library.reservations', ['reservations' => $reservations]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('library.issue');
        $data = $request->validate(['library_book_id' => ['required', 'integer', TenantRule::exists('library_books', 'id', true)], 'member' => ['required', 'string']]);
        $parsed = LibraryMembers::parse($data['member']) ?? throw ValidationException::withMessages(['member' => __('Choose a member.')]);
        $this->library->reserve(LibraryBook::query()->findOrFail($data['library_book_id']), $parsed[0], $parsed[1]);

        return back()->with('success', __('Reservation placed.'));
    }

    public function cancel(LibraryReservation $reservation): RedirectResponse
    {
        $this->authorize('library.issue');
        try {
            $this->library->cancelReservation($reservation);
        } catch (ValidationException $e) {
            return back()->with('warning', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Reservation cancelled.'));
    }
}
