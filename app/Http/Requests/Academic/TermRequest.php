<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('session'));
    }

    public function rules(): array
    {
        $session = $this->route('session');

        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('terms', 'name')->where('academic_session_id', $session->id)],
            'starts_on' => ['nullable', 'date', 'after_or_equal:'.$session->starts_on->toDateString()],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'before_or_equal:'.$session->ends_on->toDateString()],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
