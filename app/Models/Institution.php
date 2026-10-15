<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'name_bn', 'slug', 'code', 'email', 'phone', 'website', 'address',
        'logo_path', 'favicon_path', 'primary_color', 'timezone', 'currency', 'locale', 'status', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'settings' => 'array',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }
}
