<?php

namespace App\Models\Library;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryCategory extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = ['name', 'name_bn'];

    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function books(): HasMany
    {
        return $this->hasMany(LibraryBook::class);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
