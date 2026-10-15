<?php

namespace App\Http\Controllers\Branch\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StudentDocumentRequest;
use App\Models\Students\Student;
use App\Models\Students\StudentDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Private document storage: tenant-prefixed path on the local (non-public) disk, authorised download. */
class StudentDocumentController extends Controller
{
    public function store(StudentDocumentRequest $request, Student $student): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store(tenant()->branch()->storagePath('students/'.$student->id.'/documents'), 'local');

        $student->documents()->create([
            'type' => $request->validated('type'),
            'title' => $request->validated('title'),
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 191),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', __('Document uploaded.'));
    }

    public function download(Student $student, StudentDocument $document): StreamedResponse
    {
        $this->authorize('view', $student);
        abort_unless($document->student_id === $student->id, 404);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Student $student, StudentDocument $document): RedirectResponse
    {
        $this->authorize('update', $student);
        abort_unless($document->student_id === $student->id, 404);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return back()->with('success', __('Document deleted.'));
    }
}
