<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** Science / Humanities / Business Studies … (table "study_groups": GROUPS is reserved in MySQL 8). */
class StudyGroup extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['name', 'name_bn', 'code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
