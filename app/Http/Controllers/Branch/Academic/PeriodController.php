<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Controller;
use App\Models\Academic\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Bell schedule: periods and breaks per shift. */
class PeriodController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('timetables.view');

        return view('branch.academics.periods.index', [
            'periods' => Period::query()->ordered()->get(),
            'editing' => $request->integer('edit') ? Period::query()->find($request->integer('edit')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('timetables.create');
        Period::query()->create($this->validated($request));

        return redirect()->route('branch.academics.periods.index')->with('success', __('Period saved.'));
    }

    public function update(Request $request, Period $period): RedirectResponse
    {
        $this->authorize('timetables.update');
        $period->update($this->validated($request));

        return redirect()->route('branch.academics.periods.index')->with('success', __('Period saved.'));
    }

    public function destroy(Period $period): RedirectResponse
    {
        $this->authorize('timetables.delete');
        $period->delete(); // routine entries of the period are removed with it (cascade)

        return back()->with('success', __('Period deleted.'));
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'shift' => ['required', Rule::in(Period::SHIFTS)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'is_break' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99'],
        ]);

        return $data + ['is_break' => $request->boolean('is_break'), 'sort_order' => (int) ($data['sort_order'] ?? 0)];
    }
}
