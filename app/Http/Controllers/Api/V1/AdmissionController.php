<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admissions\AdmissionApplication;
use App\Models\Admissions\AdmissionCampaign;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/admissions                 admission rounds of the branch
 * GET /api/v1/admissions/{id}/applications?status=&q=   applications of one round (no documents, no IP)
 */
class AdmissionController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $this->authorize('admissions.view');

        return $this->ok(AdmissionCampaign::query()->with(['schoolClass:id,name', 'session:id,name'])->latest('starts_on')->get()
            ->map(fn (AdmissionCampaign $c) => [
                'id' => $c->id, 'title' => $c->title, 'class' => $c->schoolClass?->name, 'session' => $c->session?->name,
                'starts_on' => $c->starts_on->toDateString(), 'ends_on' => $c->ends_on->toDateString(), 'seats' => $c->seats,
                'status' => $c->status, 'is_online' => $c->is_online, 'has_test' => $c->has_test, 'admission_fee' => (float) $c->admission_fee,
                'merit_published' => $c->merit_published,
            ]));
    }

    public function applications(Request $request, AdmissionCampaign $campaign): JsonResponse
    {
        $this->authorize('admissions.view');

        $page = $campaign->applications()->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByRaw('merit_position IS NULL')->orderBy('merit_position')->latest('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->ok($page->getCollection()->map(fn (AdmissionApplication $a) => [
            'id' => $a->id, 'application_no' => $a->application_no, 'source' => $a->source, 'status' => $a->status,
            'name' => $a->name, 'gender' => $a->gender, 'date_of_birth' => $a->date_of_birth->toDateString(),
            'guardian_name' => $a->guardian_name, 'guardian_phone' => $a->guardian_phone,
            'test_marks' => $a->test_marks, 'test_absent' => $a->test_absent, 'merit_position' => $a->merit_position,
            'waiting_position' => $a->waiting_position, 'fee_status' => $a->fee_status, 'student_id' => $a->student_id,
        ]), null, 200, ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]);
    }
}
