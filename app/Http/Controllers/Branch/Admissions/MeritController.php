<?php

namespace App\Http\Controllers\Branch\Admissions;

use App\Http\Controllers\Controller;
use App\Models\Admissions\AdmissionApplication;
use App\Models\Admissions\AdmissionCampaign;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admission test marks, merit & waiting list, publication. */
class MeritController extends Controller
{
    public function __construct(protected AdmissionService $admissions) {}

    public function marks(AdmissionCampaign $campaign): View
    {
        $this->authorize('admissions.update');
        abort_unless($campaign->has_test, 404);

        return view('branch.admissions.merit.marks', [
            'campaign' => $campaign->load('schoolClass:id,name'),
            'applications' => $campaign->applications()->whereIn('status', AdmissionApplication::IN_RACE)->orderBy('application_no')->get(),
        ]);
    }

    public function storeMarks(Request $request, AdmissionCampaign $campaign): RedirectResponse
    {
        $this->authorize('admissions.update');

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.marks' => ['nullable', 'numeric', 'min:0'],
            'rows.*.absent' => ['nullable', 'boolean'],
        ]);

        $saved = $this->admissions->recordMarks($campaign, $data['rows'], $request->user());

        return back()->with('success', __(':count mark(s) saved.', ['count' => $saved]));
    }

    public function show(AdmissionCampaign $campaign): View
    {
        $this->authorize('admissions.view');

        return view('branch.admissions.merit.show', [
            'campaign' => $campaign->load(['schoolClass:id,name', 'session:id,name']),
            'selected' => $campaign->applications()->whereIn('status', ['selected', 'admitted'])->orderBy('merit_position')->get(),
            'waiting' => $campaign->applications()->where('status', 'waiting')->orderBy('waiting_position')->get(),
        ]);
    }

    public function generate(Request $request, AdmissionCampaign $campaign): RedirectResponse
    {
        $this->authorize('admissions.approve');
        $stats = $this->admissions->generateMerit($campaign, $request->user());

        return redirect()->route('branch.admissions.merit.show', $campaign)
            ->with('success', __('Merit list ready: :selected selected, :waiting waiting, :rejected not selected.', $stats));
    }

    public function promote(Request $request, AdmissionCampaign $campaign): RedirectResponse
    {
        $this->authorize('admissions.approve');
        $next = $this->admissions->promoteFromWaiting($campaign, $request->user());

        return back()->with($next ? 'success' : 'error', $next
            ? __(':name (:no) moved from the waiting list to selected.', ['name' => $next->name, 'no' => $next->application_no])
            : __('The waiting list is empty.'));
    }

    public function publish(Request $request, AdmissionCampaign $campaign): RedirectResponse
    {
        $this->authorize('admissions.approve');
        $campaign->update(['merit_published' => ! $campaign->merit_published]);

        return back()->with('success', $campaign->merit_published ? __('Result published on the admission page.') : __('Result hidden from the admission page.'));
    }
}
