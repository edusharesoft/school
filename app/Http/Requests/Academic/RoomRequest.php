<?php

namespace App\Http\Requests\Academic;

use App\Models\Academic\Room;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->route('room');

        return $model instanceof Room ? $this->user()->can('update', $model) : $this->user()->can('create', Room::class);
    }

    public function rules(): array
    {
        $id = $this->route('room')?->id;

        return [
            'name' => ['required', 'string', 'max:60'],
            'code' => ['required', 'alpha_dash:ascii', 'max:20', TenantRule::unique('rooms', 'code', $id)],
            'building' => ['nullable', 'string', 'max:60'],
            'type' => ['required', Rule::in(Room::TYPES)],
            'capacity' => ['required', 'integer', 'min:1', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
