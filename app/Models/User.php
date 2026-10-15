<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Enums\UserType;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'name_bn', 'email', 'username', 'phone', 'user_type', 'status', 'locale', 'avatar_path', 'password',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected array $auditExclude = ['remember_token', 'last_login_at', 'last_login_ip', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_step'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'institution_id' => 'integer',
            'default_branch_id' => 'integer',
            'user_type' => UserType::class,
            'status' => RecordStatus::class,
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }

    // ------------------------------------------------------------------ relations

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function defaultBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot(['is_default', 'granted_by'])->withTimestamps();
    }

    // ------------------------------------------------------------------ scopes

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('username', 'like', "%{$term}%");
        });
    }

    /** Users who may work in the given branch (explicit membership or institution-wide access). */
    public function scopeWithAccessToBranch(Builder $query, Branch $branch): Builder
    {
        return $query->where('institution_id', $branch->institution_id)
            ->whereHas('branches', fn (Builder $q) => $q->where('branches.id', $branch->id));
    }

    // ------------------------------------------------------------------ helpers

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /** Configured roles (config/security.php) must have two-factor switched on. */
    public function requiresTwoFactor(): bool
    {
        $roles = (array) config('security.two_factor.required_roles', []);

        return $roles !== [] && $this->hasAnyRole($roles);
    }

    /** SaaS operator account: not bound to any institution. */
    public function isPlatformOwner(): bool
    {
        return $this->institution_id === null && $this->isSuperAdmin();
    }

    public function canAccessAllBranches(): bool
    {
        return $this->isSuperAdmin() || $this->checkPermissionTo('branches.access-all');
    }

    public function canUseCentral(): bool
    {
        if ($this->isPlatformOwner()) {
            return true;
        }

        // A suspended institution loses central access as well as branch access.
        if (! $this->institution?->isActive()) {
            return false;
        }

        return $this->isSuperAdmin() || $this->checkPermissionTo('central.access');
    }

    /** Institution-level rule: may this user work inside the given branch? */
    public function canAccessBranch(Branch $branch): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->isPlatformOwner()) {
            return true;
        }

        if ($this->institution_id !== $branch->institution_id) {
            return false;
        }

        if ($this->canAccessAllBranches()) {
            return true;
        }

        return $this->branches()->where('branches.id', $branch->id)->exists();
    }

    /** Portal the user lands on: admin | teacher | student | parent. */
    public function portal(): string
    {
        return match ($this->user_type) {
            UserType::Teacher => 'teacher',
            UserType::Student => 'student',
            UserType::Parent => 'parent',
            default => 'admin',
        };
    }

    public function roleLabels(): string
    {
        return $this->roles->map(fn ($role) => $role->label ?: $role->name)->implode(', ');
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }
}
