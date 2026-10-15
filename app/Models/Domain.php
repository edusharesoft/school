<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use Auditable;

    protected $fillable = ['domain', 'type', 'is_primary', 'verified_at'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'institution_id' => 'integer',
            'branch_id' => 'integer',
        ];
    }

    protected function setDomainAttribute(string $value): void
    {
        $this->attributes['domain'] = strtolower(trim($value));
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
