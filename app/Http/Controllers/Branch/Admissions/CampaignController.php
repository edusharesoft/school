<?php

namespace App\Http\Controllers\Branch\Admissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\AdmissionCampaignRequest;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Admissions\AdmissionCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Admission rounds: class, session, dates, seats, test, fee, required documents and form fields. */
class CampaignController extends Controller
{
    public function index(): View
    {
        $this->authorize('admissions.view');

        $campaigns = AdmissionCampaign::query()->with(['session:id,name', 'schoolClass:id,name'])->latest('starts_on')->paginate(20);
        $counts = DB::table('admission_applications')->whereIn('admission_campaign_id', $campaigns->pluck('id'))->whereNull('deleted_at')
            ->select('admission_campaign_id', 'status', DB::raw('COUNT(*) as total'))->groupBy('admission_campaign_id', 'status')->get()
            ->groupBy('admission_campaign_id')->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($v) => (int) $v)->all());

        return view('branch.admissions.campaigns.index', compact('campaigns', 'counts'));
    }

    public function create(): View
    {
        $this->authorize('admissions.create');

        return view('branch.admissions.campaigns.form', $this->options() + ['campaign' => new AdmissionCampaign([
            'starts_on' => now(), 'ends_on' => now()->addMonth(), 'seats' => 40, 'is_online' => true, 'admission_fee' => 0,
            'status' => 'draft', 'required_documents' => ['birth_certificate'],
            'form_settings' => ['birth_registration_no' => 'required', 'photo' => 'required'],
        ])]);
    }

    public function store(AdmissionCampaignRequest $request): RedirectResponse
    {
        $campaign = AdmissionCampaign::query()->create($request->campaignData());

        return redirect()->route('branch.admissions.applications.index', ['campaign_id' => $campaign->id])->with('success', __('Admission created.'));
    }

    public function edit(AdmissionCampaign $campaign): View
    {
        $this->authorize('admissions.update');

        return view('branch.admissions.campaigns.form', $this->options() + ['campaign' => $campaign]);
    }

    public function update(AdmissionCampaignRequest $request, AdmissionCampaign $campaign): RedirectResponse
    {
        $campaign->update($request->campaignData());

        return redirect()->route('branch.admissions.campaigns.index')->with('success', __('Admission updated.'));
    }

    protected function options(): array
    {
        return [
            'sessions' => AcademicSession::query()->where('status', 'open')->latest('starts_on')->pluck('name', 'id'),
            'classes' => SchoolClass::query()->active()->ordered()->pluck('name', 'id'),
        ];
    }
}
