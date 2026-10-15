<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Term extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['academic_session_id', 'name', 'starts_on', 'ends_on', 'sort_order'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sort_order' => 'integer',
            'branch_id' => 'integer',
            'institution_id' => 'integer',
            'academic_session_id' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }
}
