<?php

namespace App\Models\Saas;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A subscription plan sold by the platform owner (limits are per institution). */
class Plan extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'name_bn', 'description', 'price_monthly', 'price_yearly', 'max_branches', 'max_students', 'trial_days', 'grace_days', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price_monthly' => 'decimal:2', 'price_yearly' => 'decimal:2', 'max_branches' => 'integer', 'max_students' => 'integer',
            'trial_days' => 'integer', 'grace_days' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }

    public function limitLabel(?int $limit): string
    {
        return $limit === null ? __('Unlimited') : number_format($limit);
    }
}
