<?php

namespace App\Models\Admissions;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Private upload attached to an application (local disk, tenant-prefixed path). */
class AdmissionDocument extends Model
{
    use BelongsToBranch;

    protected $fillable = ['admission_application_id', 'type', 'title', 'disk', 'path', 'original_name', 'mime_type', 'size'];

    protected $hidden = ['path', 'disk'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'admission_application_id' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
