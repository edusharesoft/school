<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\HR\Employee
 * Salary, NID, addresses and document paths are never exposed through the API.
 */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_code' => $this->employee_code,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'employee_type' => $this->employee_type,
            'employment_type' => $this->employment_type,
            'gender' => $this->gender,
            'designation' => $this->whenLoaded('designation', fn () => $this->designation?->name),
            'department' => $this->whenLoaded('department', fn () => $this->department?->name),
            'phone' => $this->phone,
            'email' => $this->email,
            'joining_date' => $this->joining_date?->toDateString(),
            'status' => $this->status,
        ];
    }
}
