<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AcademicYearRequest;
use App\Models\Academic\AcademicYear;
use App\Models\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Institution-wide academic years (branches create their own sessions inside a year). */
class AcademicYearController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AcademicYear::class);

        $years = AcademicYear::query()->with('institution:id,name')->withCount('sessions')->latest('starts_on')->paginate(20);

        return view('central.academic-years.index', compact('years'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', AcademicYear::class);

        $year = now()->year;

        return view('central.academic-years.form', [
            'year' => new AcademicYear(['name' => (string) $year, 'starts_on' => "{$year}-01-01", 'ends_on' => "{$year}-12-31"]),
            'institutions' => $request->user()->isPlatformOwner() ? Institution::query()->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $year = new AcademicYear($request->safe()->except(['institution_id', 'is_current']));
            $year->institution_id = $request->targetInstitutionId();
            $year->is_current = false;
            $year->save();

            if ($request->boolean('is_current')) {
                $this->makeCurrent($year);
            }
        });

        return redirect()->route('central.academic-years.index')->with('success', __('Academic year created.'));
    }

    public function edit(AcademicYear $academicYear): View
    {
        $this->authorize('update', $academicYear);

        return view('central.academic-years.form', ['year' => $academicYear, 'institutions' => collect()]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($request, $academicYear) {
            $academicYear->update($request->safe()->except(['institution_id', 'is_current']));
            if ($request->boolean('is_current')) {
                $this->makeCurrent($academicYear);
            }
        });

        return redirect()->route('central.academic-years.index')->with('success', __('Academic year updated.'));
    }

    protected function makeCurrent(AcademicYear $year): void
    {
        AcademicYear::query()->where('institution_id', $year->institution_id)->whereKeyNot($year->id)->update(['is_current' => false]);
        $year->update(['is_current' => true]);
    }
}
