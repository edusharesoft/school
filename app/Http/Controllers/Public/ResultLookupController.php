<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Branch\Exams\ResultController;
use App\Http\Controllers\Controller;
use App\Models\Exams\ExamResult;
use App\Models\Exams\Examination;
use App\Models\Students\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Public result lookup on the branch host: published exam + student ID + date of birth.
 * POST so the date of birth never appears in URLs or logs; rate-limited ("public-lookup").
 */
class ResultLookupController extends Controller
{
    public function index(): View
    {
        return view('public.results', ['exams' => $this->exams()]);
    }

    public function lookup(Request $request): View
    {
        $data = $request->validate([
            'examination_id' => ['required', 'integer'],
            'student_code' => ['required', 'string', 'max:40'],
            'date_of_birth' => ['required', 'date'],
        ]);

        $exam = $this->exams()->firstWhere('id', (int) $data['examination_id']);
        $student = Student::query()->where('student_code', strtoupper(trim($data['student_code'])))
            ->whereDate('date_of_birth', Carbon::parse($data['date_of_birth'])->toDateString())->first();
        $result = $exam && $student ? ExamResult::query()->where('examination_id', $exam->id)->where('student_id', $student->id)->first() : null;

        if (! $result) {
            return view('public.results', ['exams' => $this->exams(), 'notFound' => true]);
        }

        return view('branch.exams.results.marksheets', ResultController::marksheetData($exam, collect([$result])) + ['back' => route('results.index')]);
    }

    protected function exams()
    {
        return Examination::query()->where('status', 'published')->latest('published_at')->limit(20)->get(['id', 'name', 'status', 'published_at', 'academic_session_id', 'grading_scale_id']);
    }
}
