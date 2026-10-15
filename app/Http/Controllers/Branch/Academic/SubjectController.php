<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SubjectRequest;
use App\Models\Academic\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Subject::class);

        $items = Subject::query()
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('branch.academics.subjects.index', ['items' => $items]);
    }

    public function create(): View
    {
        $this->authorize('create', Subject::class);

        return view('branch.academics.subjects.form', ['item' => new Subject(['type' => 'theory', 'is_active' => true])]);
    }

    public function store(SubjectRequest $request): RedirectResponse
    {
        Subject::query()->create($this->data($request));

        return redirect()->route('branch.academics.subjects.index')->with('success', __('Subject created.'));
    }

    public function edit(Subject $subject): View
    {
        $this->authorize('update', $subject);

        return view('branch.academics.subjects.form', ['item' => $subject]);
    }

    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update($this->data($request));

        return redirect()->route('branch.academics.subjects.index')->with('success', __('Subject updated.'));
    }

    public function destroy(Subject $subject): RedirectResponse
    {
        $this->authorize('delete', $subject);

        if ($subject->classSubjects()->exists()) {
            return back()->withErrors(['subject' => __('Remove this subject from all classes before deleting it.')]);
        }

        $subject->delete();

        return redirect()->route('branch.academics.subjects.index')->with('success', __('Subject deleted.'));
    }

    protected function data(SubjectRequest $request): array
    {
        return array_merge($request->validated(), [
            'code' => strtoupper((string) $request->validated('code')),
            'is_active' => $request->boolean('is_active'),
        ]);
    }
}
