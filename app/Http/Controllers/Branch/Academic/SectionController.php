<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SectionRequest;
use App\Models\Academic\Room;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Section;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function create(Request $request): View
    {
        $this->authorize('create', Section::class);

        return view('branch.academics.sections.form', $this->formData(new Section([
            'school_class_id' => $request->integer('class') ?: null, 'capacity' => 50, 'is_active' => true,
        ])));
    }

    public function store(SectionRequest $request): RedirectResponse
    {
        Section::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('branch.academics.classes.index')->with('success', __('Section created.'));
    }

    public function edit(Section $section): View
    {
        $this->authorize('update', $section);

        return view('branch.academics.sections.form', $this->formData($section));
    }

    public function update(SectionRequest $request, Section $section): RedirectResponse
    {
        $section->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('branch.academics.classes.index')->with('success', __('Section updated.'));
    }

    public function destroy(Section $section): RedirectResponse
    {
        $this->authorize('delete', $section);

        if ($section->enrollments()->exists()) {
            return back()->withErrors(['section' => __('This section has enrollment history and cannot be deleted. Mark it inactive instead.')]);
        }

        $section->delete();

        return back()->with('success', __('Section deleted.'));
    }

    protected function formData(Section $section): array
    {
        $branch = tenant()->branch();

        return [
            'section' => $section,
            'classes' => SchoolClass::query()->ordered()->pluck('name', 'id'),
            'rooms' => Room::query()->where('is_active', true)->orderBy('code')->pluck('name', 'id'),
            // Teachers of this branch (explicit branch membership).
            'teachers' => User::query()->withAccessToBranch($branch)->where('user_type', 'teacher')->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
