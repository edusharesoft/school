<?php

namespace App\Models\HR;

use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use BelongsToBranch;

    public const TYPES = ['cv', 'certificate', 'nid', 'appointment_letter', 'experience_letter', 'other'];

    protected $fillable = ['employee_id', 'type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'employee_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
