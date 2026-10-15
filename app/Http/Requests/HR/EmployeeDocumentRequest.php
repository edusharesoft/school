<?php

namespace App\Http\Requests\HR;

use App\Models\HR\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employee'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(EmployeeDocument::TYPES)],
            'title' => ['required', 'string', 'max:191'],
            // MIME is checked from file content (fileinfo), not the extension.
            'file' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp', 'max:5120'],
        ];
    }
}
