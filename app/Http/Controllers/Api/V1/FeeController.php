<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Finance\FeeInvoice;
use App\Models\Finance\FeePayment;
use App\Services\Portal\PortalService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/fees/invoices?student_id=&status=&period=   invoices (invoices.view)
 * GET /api/v1/payments?from=&to=&student_id=              receipts (payments.view)
 * GET /api/v1/my/fees                                     the signed-in student's / parent's children's invoices & receipts
 */
class FeeController extends Controller
{
    use ApiResponse;

    public function invoices(Request $request): JsonResponse
    {
        $this->authorize('invoices.view');
        $page = FeeInvoice::query()->with('student:id,name,student_code')
            ->when($request->integer('student_id'), fn ($q, $id) => $q->where('student_id', $id))
            ->when($request->input('status'), fn ($q, $s) => $s === 'overdue' ? $q->overdue() : $q->where('status', $s))
            ->when($request->input('period'), fn ($q, $p) => $q->where('period', $p))
            ->latest('issue_date')->latest('id')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->ok($page->getCollection()->map(fn (FeeInvoice $i) => $this->invoice($i) + ['student' => ['id' => $i->student_id, 'name' => $i->student?->name, 'code' => $i->student?->student_code]]),
            null, 200, ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]);
    }

    public function payments(Request $request): JsonResponse
    {
        $this->authorize('payments.view');
        $page = FeePayment::query()->with('student:id,name,student_code')
            ->when($request->date('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d->toDateString()))
            ->when($request->date('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d->toDateString()))
            ->when($request->integer('student_id'), fn ($q, $id) => $q->where('student_id', $id))
            ->latest('date')->latest('id')->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->ok($page->getCollection()->map(fn (FeePayment $p) => $this->payment($p) + ['student' => ['id' => $p->student_id, 'name' => $p->student?->name, 'code' => $p->student?->student_code]]),
            null, 200, ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]);
    }

    public function mine(Request $request, PortalService $portal): JsonResponse
    {
        $user = $request->user();
        $students = collect();
        if ($student = $portal->studentFor($user)) {
            $students->push($student);
        } elseif ($guardian = $portal->guardianFor($user)) {
            $students = $portal->children($guardian);
        }
        abort_if($students->isEmpty(), 404);

        return $this->ok($students->map(function ($s) use ($portal) {
            $fees = $portal->fees($s);

            return [
                'student' => ['id' => $s->id, 'name' => $s->name, 'code' => $s->student_code],
                'due' => $fees['due'], 'overdue' => $fees['overdue'], 'advance' => $fees['credit'],
                'invoices' => $fees['invoices']->map(fn ($i) => $this->invoice($i))->values(),
                'payments' => $fees['payments']->map(fn ($p) => $this->payment($p))->values(),
            ];
        })->values());
    }

    protected function invoice(FeeInvoice $i): array
    {
        return ['id' => $i->id, 'invoice_no' => $i->invoice_no, 'title' => $i->title, 'period' => $i->period, 'issue_date' => $i->issue_date->toDateString(),
            'due_date' => $i->due_date->toDateString(), 'total' => $i->total, 'paid' => $i->paid, 'waived' => $i->waived, 'balance' => $i->balance, 'status' => $i->status];
    }

    protected function payment(FeePayment $p): array
    {
        return ['id' => $p->id, 'receipt_no' => $p->receipt_no, 'date' => $p->date->toDateString(), 'amount' => $p->amount, 'method' => $p->method,
            'reference' => $p->reference, 'status' => $p->status, 'advance_left' => $p->unallocated];
    }
}
