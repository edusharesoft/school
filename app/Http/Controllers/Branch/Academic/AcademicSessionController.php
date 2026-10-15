<?php

namespace App\Http\Controllers\Branch\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AcademicSessionRequest;
use App\Http\Requests\Academic\TermRequest;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Term;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AcademicSessionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', AcademicSession::class);

        $sessions = AcademicSession::query()->with(['academicYear', 'terms'])->withCount('enrollments')->latest('starts_on')->paginate(20);

        return view('branch.academics.sessions.index', compact('sessions'));
    }

    public function create(): View
    {
        $this->authorize('create', AcademicSession::class);

        $year = AcademicYear::query()->current()->first();

        return view('branch.academics.sessions.form', [
            'session' => new AcademicSession([
                'academic_year_id' => $year?->id, 'name' => $year?->name, 'status' => 'open',
                'starts_on' => $year?->starts_on, 'ends_on' => $year?->ends_on,
            ]),
            'years' => AcademicYear::query()->latest('starts_on')->pluck('name', 'id'),
        ]);
    }

    public function store(AcademicSessionRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $session = AcademicSession::query()->create($request->validated() + ['is_current' => false]);
            if ($request->boolean('is_current')) {
                $this->makeCurrent($session);
            }
        });

        return redirect()->route('branch.academics.sessions.index')->with('success', __('Academic session created.'));
    }

    public function edit(AcademicSession $session): View
    {
        $this->authorize('update', $session);

        return view('branch.academics.sessions.form', [
            'session' => $session->load('terms'),
            'years' => AcademicYear::query()->latest('starts_on')->pluck('name', 'id'),
        ]);
    }

    public function update(AcademicSessionRequest $request, AcademicSession $session): RedirectResponse
    {
        DB::transaction(function () use ($request, $session) {
            $session->update(collect($request->validated())->except('is_current')->all());
            if ($request->boolean('is_current') && ! $session->is_current) {
                $this->makeCurrent($session);
            }
        });

        return redirect()->route('branch.academics.sessions.index')->with('success', __('Academic session updated.'));
    }

    public function storeTerm(TermRequest $request, AcademicSession $session): RedirectResponse
    {
        $session->terms()->create($request->validated());

        return back()->with('success', __('Term added.'));
    }

    public function destroyTerm(AcademicSession $session, Term $term): RedirectResponse
    {
        $this->authorize('update', $session);
        abort_unless($term->academic_session_id === $session->id, 404);

        $term->delete();

        return back()->with('success', __('Term removed.'));
    }

    /** Exactly one current session per branch. */
    protected function makeCurrent(AcademicSession $session): void
    {
        AcademicSession::query()->whereKeyNot($session->id)->where('is_current', true)->update(['is_current' => false]);
        $session->update(['is_current' => true]);
    }
}
