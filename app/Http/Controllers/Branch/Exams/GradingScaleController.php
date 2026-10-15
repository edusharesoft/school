<?php

namespace App\Http\Controllers\Branch\Exams;

use App\Http\Controllers\Controller;
use App\Models\Exams\GradingScale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Configurable grading scales (percentage bands → grade & point, GPA rules). */
class GradingScaleController extends Controller
{
    public function index(): View
    {
        $this->authorize('examinations.view');
        GradingScale::defaultForBranch(); // make sure a branch always has one scale

        return view('branch.exams.grading.index', ['scales' => GradingScale::query()->orderByDesc('is_default')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->authorize('examinations.create');

        return view('branch.exams.grading.form', ['scale' => new GradingScale([
            'grades' => GradingScale::BANGLADESH_DEFAULT, 'max_gpa' => 5, 'optional_base_point' => 2, 'gpa_decimals' => 2, 'rounding' => 'round',
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('examinations.create');
        $this->persist(new GradingScale, $request);

        return redirect()->route('branch.exams.grading.index')->with('success', __('Grading scale saved.'));
    }

    public function edit(GradingScale $grading): View
    {
        $this->authorize('examinations.update');

        return view('branch.exams.grading.form', ['scale' => $grading]);
    }

    public function update(Request $request, GradingScale $grading): RedirectResponse
    {
        $this->authorize('examinations.update');
        $this->persist($grading, $request);

        return redirect()->route('branch.exams.grading.index')->with('success', __('Grading scale saved.'));
    }

    protected function persist(GradingScale $scale, Request $request): void
    {
        // Blank rows of the form are ignored; keys are kept so errors point at the right row.
        $request->merge(['grades' => array_filter((array) $request->input('grades', []), fn ($g) => is_array($g)
            && collect($g)->except('remark')->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty())]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'max_gpa' => ['required', 'numeric', 'min:1', 'max:10'],
            'optional_base_point' => ['required', 'numeric', 'min:0', 'max:10'],
            'gpa_decimals' => ['required', 'integer', 'min:0', 'max:4'],
            'rounding' => ['required', Rule::in(['round', 'floor'])],
            'is_default' => ['nullable', 'boolean'],
            'grades' => ['required', 'array', 'min:2', 'max:15'],
            'grades.*.grade' => ['required', 'string', 'max:5'],
            'grades.*.min' => ['required', 'numeric', 'min:0', 'max:100'],
            'grades.*.max' => ['required', 'numeric', 'min:0', 'max:100'],
            'grades.*.point' => ['required', 'numeric', 'min:0', 'max:10'],
            'grades.*.remark' => ['nullable', 'string', 'max:40'],
        ]);

        $grades = collect($data['grades'])->map(fn ($g) => [
            'grade' => trim($g['grade']), 'min' => (float) $g['min'], 'max' => (float) $g['max'], 'point' => (float) $g['point'], 'remark' => $g['remark'] ?? null,
        ])->sortByDesc('min')->values();

        $errors = [];
        if ($grades->where('point', 0.0)->isEmpty()) {
            $errors['grades'] = __('One band must have grade point 0 (the fail grade).');
        }
        if ((float) $grades->last()['min'] > 0) {
            $errors['grades'] = __('The lowest band must start at 0 %.');
        }
        if ($grades->contains(fn ($g) => $g['max'] < $g['min'])) {
            $errors['grades'] = __('In every band the upper limit must not be below the lower limit.');
        }
        if ($grades->pluck('grade')->duplicates()->isNotEmpty()) {
            $errors['grades'] = __('Grade letters must be unique.');
        }
        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($scale, $data, $grades, $request) {
            if ($request->boolean('is_default')) {
                GradingScale::query()->where('is_default', true)->when($scale->exists, fn ($q) => $q->whereKeyNot($scale->id))->update(['is_default' => false]);
            }
            $scale->fill(['grades' => $grades->all(), 'is_default' => $request->boolean('is_default')] + collect($data)->except(['grades', 'is_default'])->all())->save();
        });
    }
}
