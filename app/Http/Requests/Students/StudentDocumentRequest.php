<?php

namespace App\Http\Requests\Students;

use App\Models\Students\StudentDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('student'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(StudentDocument::TYPES)],
            'title' => ['required', 'string', 'max:191'],
            // MIME is checked from file content (fileinfo), not the extension.
            'file' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:5120'],
        ];
    }
}
