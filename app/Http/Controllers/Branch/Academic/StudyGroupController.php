<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\StudyGroupRequest;
use App\Models\Academic\StudyGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudyGroupController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StudyGroup::class);

        $items = StudyGroup::query()
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('branch.academics.groups.index', ['items' => $items]);
    }

    public function create(): View
    {
        $this->authorize('create', StudyGroup::class);

        return view('branch.academics.groups.form', ['item' => new StudyGroup(['is_active' => true])]);
    }

    public function store(StudyGroupRequest $request): RedirectResponse
    {
        StudyGroup::query()->create($this->data($request));

        return redirect()->route('branch.academics.groups.index')->with('success', __('Group created.'));
    }

    public function edit(StudyGroup $group): View
    {
        $this->authorize('update', $group);

        return view('branch.academics.groups.form', ['item' => $group]);
    }

    public function update(StudyGroupRequest $request, StudyGroup $group): RedirectResponse
    {
        $group->update($this->data($request));

        return redirect()->route('branch.academics.groups.index')->with('success', __('Group updated.'));
    }

    public function destroy(StudyGroup $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        if (\App\Models\Students\StudentEnrollment::query()->where('study_group_id', $group->id)->exists()) {
            return back()->withErrors(['group' => __('Students are enrolled in this group. Mark it inactive instead.')]);
        }

        $group->delete();

        return redirect()->route('branch.academics.groups.index')->with('success', __('Group deleted.'));
    }

    protected function data(StudyGroupRequest $request): array
    {
        return array_merge($request->validated(), [
            'code' => strtoupper((string) $request->validated('code')),
            'is_active' => $request->boolean('is_active'),
        ]);
    }
}
