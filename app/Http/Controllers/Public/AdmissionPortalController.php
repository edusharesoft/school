<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\AdmissionApplicationRequest;
use App\Models\Admissions\AdmissionApplication;
use App\Models\Admissions\AdmissionCampaign;
use App\Models\Students\Guardian;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public admission pages on a branch host (no login): open admissions, online form, confirmation,
 * status check (application number + mobile) and the published merit list (application numbers only).
 */
class AdmissionPortalController extends Controller
{
    public function index(): View
    {
        return view('public.admission.index', [
            'campaigns' => AdmissionCampaign::query()->with(['schoolClass:id,name', 'session:id,name'])->acceptingOnline()->orderBy('ends_on')->get(),
            'published' => AdmissionCampaign::query()->with('schoolClass:id,name')->where('merit_published', true)->latest('ends_on')->limit(10)->get(),
        ]);
    }

    public function form(AdmissionCampaign $campaign): View
    {
        abort_unless($campaign->is_online && $campaign->status === 'open', 404);

        return view('public.admission.form', [
            'campaign' => $campaign->load(['schoolClass:id,name', 'session:id,name']),
            'relations' => Guardian::RELATIONS,
            'accepting' => $campaign->isAcceptingApplications(),
        ]);
    }

    public function submit(AdmissionApplicationRequest $request, AdmissionCampaign $campaign, AdmissionService $admissions): RedirectResponse
    {
        abort_unless($campaign->is_online, 404);

        $application = $admissions->submit($campaign, $request->applicationData(), $request->documentFiles(), $request->file('photo'), 'online', null, $request->ip());

        return redirect()->route('admission.done')->with('admission_done', [
            'application_no' => $application->application_no,
            'name' => $application->name,
            'campaign' => $campaign->title,
            'fee' => (float) $campaign->admission_fee,
            'test_at' => $campaign->test_at?->format('d M Y, h:i A'),
            'test_venue' => $campaign->test_venue,
        ]);
    }

    public function done(Request $request): View|RedirectResponse
    {
        $info = $request->session()->get('admission_done');

        return $info ? view('public.admission.done', ['info' => $info]) : redirect()->route('admission.index');
    }

    /** Status check: both the application number and the registered mobile must match. */
    public function status(Request $request): View
    {
        $application = null;
        $searched = false;

        if ($request->filled('application_no')) {
            $data = $request->validate([
                'application_no' => ['required', 'string', 'max:30'],
                'phone' => ['required', 'string', 'max:20'],
            ]);
            $searched = true;
            $phone = preg_replace('/[\s-]/', '', $data['phone']);
            $application = AdmissionApplication::query()->with('campaign:id,title,test_at,test_venue,merit_published,admission_fee,has_test')
                ->where('application_no', strtoupper(trim($data['application_no'])))
                ->where(fn ($q) => $q->where('phone', $phone)->orWhere('guardian_phone', $phone))
                ->first();
        }

        return view('public.admission.status', compact('application', 'searched'));
    }

    public function merit(AdmissionCampaign $campaign): View
    {
        abort_unless($campaign->merit_published, 404);

        return view('public.admission.merit', [
            'campaign' => $campaign->load('schoolClass:id,name'),
            'selected' => $campaign->applications()->whereIn('status', ['selected', 'admitted'])->orderBy('merit_position')->get(['application_no', 'merit_position']),
            'waiting' => $campaign->applications()->where('status', 'waiting')->orderBy('waiting_position')->get(['application_no', 'waiting_position']),
        ]);
    }
}
