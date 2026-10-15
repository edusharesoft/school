<?php

namespace App\Models\Academic;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use Auditable, BelongsToBranch, HasFactory, SoftDeletes;

    public const TYPES = ['theory', 'practical', 'theory_practical'];

    protected $fillable = ['name', 'name_bn', 'code', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'branch_id' => 'integer', 'institution_id' => 'integer'];
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $term ? $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('name_bn', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")) : $query;
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
