<?php

namespace App\Http\Controllers\Central;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\BranchRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Institution;
use App\Services\Tenancy\BranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function __construct(protected BranchService $branches) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->visibleTo($request->user())
            ->with(['institution:id,name', 'domains'])
            ->withCount('users')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('subdomain', 'like', "%{$term}%")))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('central.branches.index', compact('branches'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Branch::class);

        return view('central.branches.form', [
            'branch' => new Branch(['timezone' => config('app.timezone'), 'currency' => config('app.currency'), 'locale' => 'en',
                'status' => 'active', 'type' => 'school_college']),
            'institutions' => $this->institutionOptions($request),
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $institution = Institution::query()->findOrFail($request->targetInstitutionId());
        $this->authorize('view', $institution);

        $branch = $this->branches->create($institution, Arr::except($request->validated(), ['logo', 'cover', 'institution_id']));
        $this->storeLogo($request, $branch);
        $this->storeCover($request, $branch);

        return redirect()->route('central.branches.show', $branch)
            ->with('success', __('Branch created. It is reachable at :host', ['host' => $branch->host()]));
    }

    public function show(Branch $branch): View
    {
        $this->authorize('view', $branch);

        $branch->load(['institution', 'domains'])->loadCount(['users', 'departments']);
        $activity = AuditLog::query()->with('user:id,name')->where('branch_id', $branch->id)->latest('id')->limit(15)->get();

        return view('central.branches.show', compact('branch', 'activity'));
    }

    public function edit(Request $request, Branch $branch): View
    {
        $this->authorize('update', $branch);

        return view('central.branches.form', ['branch' => $branch, 'institutions' => $this->institutionOptions($request)]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->branches->update($branch, Arr::except($request->validated(), ['logo', 'cover', 'institution_id', 'custom_domain']));
        $this->storeLogo($request, $branch);
        $this->storeCover($request, $branch);

        return redirect()->route('central.branches.show', $branch)->with('success', __('Branch updated.'));
    }

    public function toggleStatus(Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);

        $new = $branch->isActive() ? RecordStatus::Inactive : RecordStatus::Active;
        $this->branches->setStatus($branch, $new);

        return back()->with('success', $new === RecordStatus::Active ? __('Branch activated.') : __('Branch deactivated.'));
    }

    protected function institutionOptions(Request $request)
    {
        $user = $request->user();

        return Institution::query()
            ->when(! $user->isPlatformOwner(), fn ($q) => $q->whereKey($user->institution_id))
            ->orderBy('name')->pluck('name', 'id');
    }

    protected function storeLogo(BranchRequest $request, Branch $branch): void
    {
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('branches/'.$branch->id, 'public');
            $branch->update(['logo_path' => $path]);
        }
    }

    protected function storeCover(BranchRequest $request, Branch $branch): void
    {
        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('branches/'.$branch->id, 'public');
            $branch->update(['cover_path' => $path]);
        }
    }
}
