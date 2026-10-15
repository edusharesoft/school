<?php

namespace App\Http\Controllers\Branch\Students;

use App\Http\Controllers\Controller;
use App\Models\Students\Guardian;
use App\Models\Students\Student;
use App\Services\Students\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentGuardianController extends Controller
{
    public function store(Request $request, Student $student, StudentService $students): RedirectResponse
    {
        $this->authorize('update', $student);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'name_bn' => ['nullable', 'string', 'max:191'],
            'phone' => ['required', 'regex:/^\+?[\d\s-]{6,20}$/'],
            'relation' => ['required', Rule::in(Guardian::RELATIONS)],
            'occupation' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:191'],
            'is_primary' => ['nullable', 'boolean'],
            'is_emergency_contact' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['is_primary'])) {
            $student->guardians()->newPivotStatement()->where('student_id', $student->id)->update(['is_primary' => false]);
        }

        $students->syncGuardians($student, [$data]);

        return back()->with('success', __('Guardian linked.'));
    }

    public function destroy(Student $student, Guardian $guardian): RedirectResponse
    {
        $this->authorize('update', $student);

        if ($student->guardians()->count() <= 1) {
            return back()->withErrors(['guardian' => __('A student must keep at least one guardian.')]);
        }

        $student->guardians()->detach($guardian->id);

        return back()->with('success', __('Guardian unlinked.'));
    }
}
