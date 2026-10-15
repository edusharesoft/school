<?php

namespace App\Http\Controllers\Branch\Finance;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\StudyGroup;
use App\Models\Finance\FeeHead;
use App\Models\Finance\FeeStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Class-wise, session-wise fee structure: amount of each head and the months it is billed in. */
class FeeStructureController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('fees.view');
        $sessions = AcademicSession::query()->latest('starts_on')->get(['id', 'name', 'is_current', 'starts_on']);
        $session = $request->integer('academic_session_id') ? $sessions->firstWhere('id', $request->integer('academic_session_id')) : ($sessions->firstWhere('is_current', true) ?? $sessions->first());
        $classes = SchoolClass::query()->active()->ordered()->get(['id', 'name', 'has_groups']);
        $class = $request->integer('school_class_id') ? $classes->firstWhere('id', $request->integer('school_class_id')) : $classes->first();
        $groupId = $request->integer('study_group_id') ?: null;

        $rows = $session && $class ? FeeStructure::query()->where('academic_session_id', $session->id)->where('school_class_id', $class->id)
            ->where('study_group_id', $groupId)->get()->keyBy('fee_head_id') : collect();

        return view('branch.finance.structures.index', [
            'sessions' => $sessions, 'session' => $session, 'classes' => $classes, 'class' => $class, 'groupId' => $groupId,
            'groups' => StudyGroup::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'heads' => FeeHead::query()->where('is_active', true)->ordered()->get(),
            'rows' => $rows,
            'summary' => $session ? FeeStructure::query()->with(['schoolClass:id,name,numeric_order', 'head:id,name'])->where('academic_session_id', $session->id)->get()
                ->groupBy('school_class_id') : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('fees.update');
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer'],
            'school_class_id' => ['required', 'integer'],
            'study_group_id' => ['nullable', 'integer'],
            'heads' => ['nullable', 'array'],
            'heads.*.amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'heads.*.months' => ['nullable', 'array'],
            'heads.*.months.*' => ['integer', 'between:1,12'],
        ]);
        $session = AcademicSession::query()->findOrFail($data['academic_session_id']);
        $class = SchoolClass::query()->findOrFail($data['school_class_id']);
        $groupId = ($data['study_group_id'] ?? null) ? StudyGroup::query()->findOrFail($data['study_group_id'])->id : null;
        $heads = FeeHead::query()->pluck('id')->flip();

        $errors = [];
        foreach ($data['heads'] ?? [] as $headId => $row) {
            if ((float) ($row['amount'] ?? 0) > 0 && empty($row['months'])) {
                $errors["heads.$headId.months"] = __('Tick at least one billing month for every fee with an amount.');
            }
        }
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }

        DB::transaction(function () use ($data, $session, $class, $groupId, $heads) {
            foreach ($data['heads'] ?? [] as $headId => $row) {
                if (! $heads->has((int) $headId)) {
                    continue;
                }
                $existing = FeeStructure::query()->where(['academic_session_id' => $session->id, 'school_class_id' => $class->id, 'study_group_id' => $groupId, 'fee_head_id' => (int) $headId])->first();
                $amount = round((float) ($row['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    $existing?->delete();

                    continue;
                }
                $months = array_values(array_unique(array_map('intval', $row['months'] ?? [])));
                sort($months);
                if ($existing) {
                    $existing->update(['amount' => $amount, 'months' => $months]);
                } else {
                    FeeStructure::query()->create(['academic_session_id' => $session->id, 'school_class_id' => $class->id, 'study_group_id' => $groupId,
                        'fee_head_id' => (int) $headId, 'amount' => $amount, 'months' => $months]);
                }
            }
        });

        return redirect()->route('branch.finance.structures.index', ['academic_session_id' => $session->id, 'school_class_id' => $class->id, 'study_group_id' => $groupId])
            ->with('success', __('Fee structure saved for :class.', ['class' => $class->name]));
    }

    /** Copy every structure of one class to other classes of the same session (existing rows are replaced). */
    public function copy(Request $request): RedirectResponse
    {
        $this->authorize('fees.update');
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer'],
            'from_class_id' => ['required', 'integer'],
            'to_class_ids' => ['required', 'array', 'min:1'],
            'to_class_ids.*' => ['integer'],
        ]);
        $session = AcademicSession::query()->findOrFail($data['academic_session_id']);
        $source = FeeStructure::query()->where('academic_session_id', $session->id)->where('school_class_id', $data['from_class_id'])->get();
        $targets = SchoolClass::query()->whereIn('id', $data['to_class_ids'])->whereKeyNot($data['from_class_id'])->pluck('id');

        DB::transaction(function () use ($session, $source, $targets) {
            foreach ($targets as $classId) {
                FeeStructure::query()->where('academic_session_id', $session->id)->where('school_class_id', $classId)->delete();
                foreach ($source as $s) {
                    FeeStructure::query()->create(['academic_session_id' => $session->id, 'school_class_id' => $classId, 'study_group_id' => $s->study_group_id,
                        'fee_head_id' => $s->fee_head_id, 'amount' => $s->amount, 'months' => $s->months]);
                }
            }
        });

        return back()->with('success', __('Fee structure copied to :count class(es).', ['count' => $targets->count()]));
    }
}
