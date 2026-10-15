<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Students\Student;
use App\Services\Library\LibraryService;
use App\Services\Portal\PortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/my/library — loans (with due dates and fine so far), reservations and fines of the signed-in student,
 * or of each child for a guardian. Read-only; reserving a book happens in the portal.
 */
class LibraryController extends Controller
{
    use ApiResponse;

    public function mine(Request $request, PortalService $portal, LibraryService $library): JsonResponse
    {
        $user = $request->user();
        $students = collect();
        if ($student = $portal->studentFor($user)) {
            $students->push($student);
        } elseif ($guardian = $portal->guardianFor($user)) {
            $students = $portal->children($guardian);
        }
        abort_if($students->isEmpty(), 404);

        return $this->ok($students->map(function (Student $s) use ($library) {
            $a = $library->account('student', $s->id);

            return [
                'student' => ['id' => $s->id, 'name' => $s->name, 'code' => $s->student_code],
                'limits' => $a['limits'], 'unpaid_fines' => round($a['unpaid'], 2),
                'loans' => $a['open']->map(fn ($l) => [
                    'id' => $l->id, 'title' => $l->book?->title, 'authors' => $l->book?->authors, 'accession_no' => $l->copy?->accession_no,
                    'issued_on' => $l->issued_on->toDateString(), 'due_on' => $l->due_on->toDateString(), 'renewals' => $l->renewals,
                    'overdue' => $l->isOverdue(), 'fine_so_far' => $library->accruedFine($l),
                ])->values(),
                'reservations' => $a['reservations']->map(fn ($r) => ['id' => $r->id, 'title' => $r->book?->title, 'status' => $r->status, 'ready_until' => $r->ready_until?->toDateString()])->values(),
                'fines' => $a['fines']->map(fn ($f) => ['id' => $f->id, 'kind' => $f->kind, 'amount' => (float) $f->amount, 'status' => $f->status, 'receipt_no' => $f->receipt_no])->values(),
            ];
        })->values());
    }
}
