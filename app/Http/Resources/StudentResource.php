<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Students\Student
 * Health notes, guardian NID and document paths are never exposed through the API.
 */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $enrollment = $this->relationLoaded('currentEnrollment') ? $this->currentEnrollment : null;

        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'name' => $this->name,
            'name_bn' => $this->name_bn,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'blood_group' => $this->blood_group,
            'status' => $this->status,
            'admission_date' => $this->admission_date?->toDateString(),
            'current_enrollment' => $enrollment ? [
                'session' => $enrollment->session?->name,
                'class' => $enrollment->schoolClass?->name,
                'section' => $enrollment->section?->name,
                'group' => $enrollment->studyGroup?->name,
                'roll_no' => $enrollment->roll_no,
            ] : null,
            'guardians' => $this->whenLoaded('guardians', fn () => $this->guardians->map(fn ($g) => [
                'name' => $g->name, 'phone' => $g->phone, 'relation' => $g->pivot->relation, 'is_primary' => (bool) $g->pivot->is_primary,
            ])),
        ];
    }
}
