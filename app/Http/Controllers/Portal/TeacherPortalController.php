<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Academic\Section;
use App\Models\Attendance\StudentAttendance;
use App\Models\HR\Employee;
use App\Models\HR\LeaveApplication;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Teacher portal home: own sections with today's register status, own leave. */
class TeacherPortalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('roles');

        $sections = Section::query()->with('schoolClass:id,name')->where('class_teacher_id', $user->id)->where('is_active', true)->get();
        $taken = StudentAttendance::query()->whereIn('section_id', $sections->pluck('id'))
            ->whereDate('date', today()->toDateString())->where('slot', 'day')
            ->distinct()->pluck('section_id')->all();

        $employee = Employee::query()->where('user_id', $user->id)->first();

        return view('portal.home', [
            'portal' => 'teacher',
            'user' => $user,
            'employee' => $employee,
            'sections' => $sections,
            'taken' => $taken,
            'pendingLeaves' => $employee ? LeaveApplication::query()->where('employee_id', $employee->id)->where('status', 'pending')->count() : 0,
        ]);
    }
}
