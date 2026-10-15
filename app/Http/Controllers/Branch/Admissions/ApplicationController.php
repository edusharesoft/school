<?php

namespace App\Http\Controllers\Branch\Admissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\AdmissionApplicationRequest;
use App\Models\Academic\Section;
use App\Models\Academic\StudyGroup;
use App\Models\Admissions\AdmissionApplication;
use App\Models\Admissions\AdmissionCampaign;
use App\Models\Admissions\AdmissionDocument;
use App\Models\Students\Guardian;
use App\Services\Admissions\AdmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Back-office handling of applications: list, offline entry, review, fee, admission. */
class ApplicationController extends Controller
{
    public function __construct(protected AdmissionService $admissions) {}

    public function index(Request $request): View
    {
        $this->authorize('admissions.view');

        $campaigns = AdmissionCampaign::query()->with('schoolClass:id,name')->latest('starts_on')->get();
        $campaign = $request->integer('campaign_id') ? $campaigns->firstWhere('id', $request->integer('campaign_id')) : $campaigns->first();

        $applications = AdmissionApplication::query()->with('campaign:id,title,has_test')
            ->when($campaign, fn ($q) => $q->where('admission_campaign_id', $campaign->id))
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')->toString()))
            ->orderByRaw('merit_position IS NULL')->orderBy('merit_position')->latest('id')
            ->paginate(30)->withQueryString();

        return view('branch.admissions.applications.index', compact('campaigns', 'campaign', 'applications'));
    }

    /** Offline (walk-in) application entered by the admission desk. */
    public function create(Request $request): View
    {
        $this->authorize('admissions.create');

        $campaigns = AdmissionCampaign::query()->with('schoolClass:id,name')->where('status', 'open')->latest('starts_on')->get();
        $campaign = $campaigns->firstWhere('id', $request->integer('campaign_id')) ?? $campaigns->first();

        return view('branch.admissions.applications.create', [
            'campaigns' => $campaigns,
            'campaign' => $campaign,
            'relations' => Guardian::RELATIONS,
        ]);
    }

    public function store(AdmissionApplicationRequest $request): RedirectResponse
    {
        $application = $this->admissions->submit($request->campaign(), $request->applicationData(), $request->documentFiles(),
            $request->file('photo'), 'offline', $request->user(), $request->ip());

        return redirect()->route('branch.admissions.applications.show', $application)
            ->with('success', __('Application saved. Application no: :no', ['no' => $application->application_no]));
    }

    public function show(AdmissionApplication $application): View
    {
        $this->authorize('admissions.view');

        $application->load(['campaign.schoolClass', 'campaign.session', 'documents', 'decider:id,name', 'creator:id,name', 'student:id,student_code,name']);
        $campaign = $application->campaign;

        return view('branch.admissions.applications.show', [
            'application' => $application,
            'sections' => Section::query()->where('school_class_id', $campaign->school_class_id)->where('is_active', true)->orderBy('name')->get(),
            'groups' => $campaign->schoolClass?->has_groups ? StudyGroup::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function updateStatus(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $this->authorize($request->input('status') === 'verified' ? 'admissions.update' : 'admissions.approve');

        $data = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected', 'cancelled'])],
            'remarks' => ['nullable', 'required_if:status,rejected', 'string', 'max:500'],
        ]);

        $this->admissions->setStatus($application, $data['status'], $data['remarks'] ?? null, $request->user());

        return back()->with('success', __('Application status updated.'));
    }

    public function recordFee(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $this->authorize('admissions.collect-fee');

        $data = $request->validate([
            'fee_status' => ['required', Rule::in(['paid', 'waived', 'unpaid'])],
            'fee_reference' => ['nullable', 'required_if:fee_status,paid', 'string', 'max:60'],
        ]);

        $this->admissions->recordFee($application, $data['fee_status'], $data['fee_reference'] ?? null, $request->user());

        return back()->with('success', __('Admission fee status recorded.'));
    }

    public function admit(Request $request, AdmissionApplication $application): RedirectResponse
    {
        $this->authorize('admissions.approve');
        $this->authorize('students.create');

        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'study_group_id' => ['nullable', 'integer'],
            'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $student = $this->admissions->admit($application, (int) $data['section_id'], $data['study_group_id'] ?? null, $data['roll_no'] ?? null, $request->user());

        return redirect()->route('branch.students.show', $student)
            ->with('success', __('Admitted. Student ID: :code', ['code' => $student->student_code]));
    }

    public function document(AdmissionApplication $application, AdmissionDocument $document): StreamedResponse
    {
        $this->authorize('admissions.view');
        abort_unless($document->admission_application_id === $application->id, 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function photo(AdmissionApplication $application): StreamedResponse
    {
        $this->authorize('admissions.view');
        abort_unless($application->photo_path && Storage::disk('local')->exists($application->photo_path), 404);

        return Storage::disk('local')->response($application->photo_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
