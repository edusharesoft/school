<?php

namespace App\Models\Students;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Private file (local disk, tenant-prefixed path). Downloaded only through StudentDocumentController. */
class StudentDocument extends Model
{
    use Auditable, BelongsToBranch;

    public const TYPES = ['birth_certificate', 'photo', 'previous_tc', 'marksheet', 'testimonial', 'other'];

    protected $fillable = ['student_id', 'type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected $hidden = ['path', 'disk'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'student_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
