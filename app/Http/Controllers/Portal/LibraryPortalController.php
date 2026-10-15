<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Library\LibraryBook;
use App\Models\Library\LibraryReservation;
use App\Models\Students\Student;
use App\Services\Library\LibraryService;
use App\Services\Portal\PortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Library in the student and parent portals: loans, due dates, fines and reservations.
 * Students can search the catalogue and reserve a title whose copies are all out; parents follow their children (read-only).
 */
class LibraryPortalController extends Controller
{
    public function __construct(protected PortalService $portal, protected LibraryService $library) {}

    public function student(Request $request): View
    {
        return $this->page($request, $this->me($request), 'student');
    }

    public function parent(Request $request, Student $student): View
    {
        $guardian = $this->portal->guardianFor($request->user());
        abort_unless($guardian && $this->portal->isChildOf($guardian, $student), 404);

        return $this->page($request, $student, 'parent');
    }

    public function reserve(Request $request, LibraryBook $book): RedirectResponse
    {
        $student = $this->me($request);
        try {
            $this->library->reserve($book, 'student', $student->id);
        } catch (ValidationException $e) {
            return back()->with('warning', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Reservation placed — we will tell you when the book is ready.'));
    }

    public function cancel(Request $request, LibraryReservation $reservation): RedirectResponse
    {
        $student = $this->me($request);
        abort_unless($reservation->member_type === 'student' && (int) $reservation->member_id === $student->id, 404);
        try {
            $this->library->cancelReservation($reservation);
        } catch (ValidationException $e) {
            return back()->with('warning', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('Reservation cancelled.'));
    }

    protected function page(Request $request, Student $student, string $mode): View
    {
        $books = collect();
        if ($mode === 'student' && filled($term = trim((string) $request->input('q')))) {
            $books = LibraryBook::query()->where('is_active', true)->search($term)
                ->withCount(['copies as available_count' => fn ($q) => $q->where('status', 'available'), 'copies as lendable_count' => fn ($q) => $q->whereIn('status', ['available', 'issued', 'reserved'])])
                ->orderBy('title')->limit(20)->get();
        }

        return view('portal.library', [
            'student' => $student, 'mode' => $mode, 'account' => $this->library->account('student', $student->id), 'library' => $this->library,
            'books' => $books, 'term' => $request->input('q'),
        ]);
    }

    protected function me(Request $request): Student
    {
        $student = $this->portal->studentFor($request->user());
        abort_unless($student, 403, __('Your login is not linked to a student record. Please contact the school office.'));

        return $student;
    }
}
