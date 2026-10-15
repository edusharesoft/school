<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\InstitutionRequest;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class InstitutionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Institution::class);
        $user = $request->user();

        $institutions = Institution::query()
            ->when(! $user->isPlatformOwner(), fn ($q) => $q->whereKey($user->institution_id))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->withCount('branches')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('central.institutions.index', compact('institutions'));
    }

    public function create(): View
    {
        $this->authorize('create', Institution::class);

        return view('central.institutions.form', ['institution' => new Institution([
            'timezone' => config('app.timezone'), 'currency' => config('app.currency'), 'locale' => 'en', 'status' => 'active', 'primary_color' => '#1f6f5c',
        ])]);
    }

    public function store(InstitutionRequest $request): RedirectResponse
    {
        $institution = Institution::query()->create(Arr::except($request->validated(), ['logo']));
        $this->storeLogo($request, $institution);

        return redirect()->route('central.institutions.index')->with('success', __('Institution created.'));
    }

    public function edit(Institution $institution): View
    {
        $this->authorize('update', $institution);

        return view('central.institutions.form', compact('institution'));
    }

    public function update(InstitutionRequest $request, Institution $institution): RedirectResponse
    {
        $data = Arr::except($request->validated(), ['logo']);

        // Only the platform owner can suspend an institution (SaaS billing control).
        if (! $request->user()->isPlatformOwner()) {
            unset($data['status']);
        }

        $institution->update($data);
        $this->storeLogo($request, $institution);

        return redirect()->route('central.institutions.index')->with('success', __('Institution updated.'));
    }

    protected function storeLogo(InstitutionRequest $request, Institution $institution): void
    {
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('institutions/'.$institution->id, 'public');
            $institution->update(['logo_path' => $path]);
        }
    }
}
