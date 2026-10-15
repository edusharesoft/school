<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Students\Student;
use App\Services\Operations\StudentFacilities;
use App\Services\Portal\PortalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Transport & hostel" in the student and parent portals — read-only. */
class FacilitiesPortalController extends Controller
{
    public function __construct(protected PortalService $portal, protected StudentFacilities $facilities) {}

    public function student(Request $request): View
    {
        $student = $this->portal->studentFor($request->user());
        abort_unless($student, 403, __('Your login is not linked to a student record. Please contact the school office.'));

        return $this->page($student, 'student');
    }

    public function parent(Request $request, Student $student): View
    {
        $guardian = $this->portal->guardianFor($request->user());
        abort_unless($guardian && $this->portal->isChildOf($guardian, $student), 404);

        return $this->page($student, 'parent');
    }

    protected function page(Student $student, string $mode): View
    {
        return view('portal.facilities', ['student' => $student, 'mode' => $mode] + $this->facilities->for($student));
    }
}
