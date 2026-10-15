<?php

namespace App\Http\Controllers\Branch\Students;

use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicSession;
use App\Models\Students\Student;
use App\Services\Audit\AuditLogger;
use App\Services\Students\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportExportController extends Controller
{
    public function form(): View
    {
        $this->authorize('import', Student::class);

        return view('branch.students.import', [
            'sessions' => AcademicSession::query()->where('status', 'open')->latest('starts_on')->get(['id', 'name', 'is_current']),
            'columns' => StudentImportService::COLUMNS,
        ]);
    }

    public function template(): StreamedResponse
    {
        $this->authorize('import', Student::class);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, StudentImportService::COLUMNS);
            fputcsv($out, ['Rahim Uddin', 'রহিম উদ্দিন', 'male', '2014-03-15', '20141234567890123', 'B+', 'Islam', '',
                'Mirpur, Dhaka', now()->toDateString(), 'VI', 'A', '', '', 'Karim Uddin', '+8801711000000', 'father']);
            fclose($out);
        }, 'student-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request, StudentImportService $importer, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('import', Student::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'academic_session_id' => ['required', 'integer', \App\Support\TenantRule::exists('academic_sessions')],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $session = AcademicSession::query()->findOrFail($data['academic_session_id']);
        $result = $importer->import($request->file('file')->getRealPath(), $session, $request->boolean('dry_run'));

        if (! $request->boolean('dry_run') && $result['ok']) {
            $audit->log('students.imported', $session, [], ['rows' => $result['imported']]);
        }

        return back()->with('import_result', $result)->with(
            $result['ok'] ? 'success' : 'warning',
            $result['ok']
                ? ($request->boolean('dry_run') ? __('Validation passed for :n rows. Nothing was saved.', ['n' => $result['total']])
                    : __(':n students imported.', ['n' => $result['imported']]))
                : __('The file has errors. Nothing was imported.')
        );
    }

    /** CSV export of the current filter (UTF-8 BOM for Excel). */
    public function export(Request $request, StudentController $list): StreamedResponse
    {
        $this->authorize('export', Student::class);

        $sessionId = $request->integer('session_id') ?: AcademicSession::currentForBranch()?->id;
        $query = $list->filteredQuery($request, $sessionId)
            ->with(['enrollments' => fn ($q) => $q->where('academic_session_id', $sessionId)->with(['schoolClass:id,name', 'section:id,name', 'studyGroup:id,name']),
                'guardians']);

        app(AuditLogger::class)->log('students.exported', null, [], ['filters' => $request->query()]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['student_code', 'name', 'name_bn', 'gender', 'date_of_birth', 'class', 'section', 'group', 'roll_no',
                'status', 'phone', 'guardian_name', 'guardian_phone', 'admission_date']);

            $query->chunk(500, function ($students) use ($out) {
                foreach ($students as $s) {
                    $e = $s->enrollments->first();
                    $g = $s->primaryGuardian();
                    fputcsv($out, [$s->student_code, $s->name, $s->name_bn, $s->gender, $s->date_of_birth?->toDateString(),
                        $e?->schoolClass?->name, $e?->section?->name, $e?->studyGroup?->name, $e?->roll_no, $s->status, $s->phone,
                        $g?->name, $g?->phone, $s->admission_date?->toDateString()]);
                }
            });
            fclose($out);
        }, 'students-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
