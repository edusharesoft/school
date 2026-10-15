<?php

namespace App\Http\Controllers\Branch\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\EmployeeDocumentRequest;
use App\Models\HR\Employee;
use App\Models\HR\EmployeeDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Private employee documents: tenant-prefixed path on the local (non-public) disk, authorised download. */
class EmployeeDocumentController extends Controller
{
    public function store(EmployeeDocumentRequest $request, Employee $employee): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store(tenant()->branch()->storagePath('employees/'.$employee->id.'/documents'), 'local');

        $employee->documents()->create([
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

    public function download(Employee $employee, EmployeeDocument $document): StreamedResponse
    {
        $this->authorize('view', $employee);
        abort_unless($document->employee_id === $employee->id, 404);
        // NID copies are confidential like the NID number itself.
        if ($document->type === 'nid') {
            $this->authorize('viewConfidential', $employee);
        }

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }

    public function destroy(Employee $employee, EmployeeDocument $document): RedirectResponse
    {
        $this->authorize('update', $employee);
        abort_unless($document->employee_id === $employee->id, 404);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return back()->with('success', __('Document deleted.'));
    }
}
