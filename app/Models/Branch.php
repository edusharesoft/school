<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Branch extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'name_bn', 'code', 'subdomain', 'type', 'email', 'phone', 'address', 'principal_name',
        'logo_path', 'cover_path', 'latitude', 'longitude', 'primary_color', 'timezone', 'currency', 'locale', 'status', 'established_on', 'sort_order', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'established_on' => 'date',
            'settings' => 'array',
            'sort_order' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'institution_id' => 'integer',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['is_default', 'granted_by'])->withTimestamps();
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Active->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Branches the given user may see in lists/selectors. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isPlatformOwner()) {
            return $query;
        }

        $query->where('institution_id', $user->institution_id);

        if (! $user->canAccessAllBranches()) {
            $query->whereIn('id', $user->branches()->select('branches.id'));
        }

        return $query;
    }

    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }

    public function host(): string
    {
        $primary = $this->relationLoaded('domains')
            ? $this->domains->firstWhere('is_primary', true)
            : $this->domains()->where('is_primary', true)->first();

        return $primary?->domain ?? $this->subdomain.'.'.config('tenancy.base_domain');
    }

    /** Absolute URL on this branch's own host (used for switching / "open branch"). */
    public function url(string $path = '/'): string
    {
        $scheme = config('tenancy.url_scheme') ?: (app()->runningInConsole() ? 'https' : request()->getScheme());
        $port = config('tenancy.url_port');
        if (! $port && ! app()->runningInConsole()) {
            $requestPort = request()->getPort();
            $port = in_array($requestPort, [80, 443], true) ? null : $requestPort;
        }

        return $scheme.'://'.$this->host().($port ? ':'.$port : '').'/'.ltrim($path, '/');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** Campus photo shown on the public website (null → the view renders a branded placeholder). */
    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** What Google Maps should look for: the pin when there are coordinates, otherwise the written address. */
    public function mapQuery(): ?string
    {
        if ($this->hasCoordinates()) {
            return (float) $this->latitude.','.(float) $this->longitude;
        }
        $address = trim((string) $this->address);

        return $address === '' ? null : trim($this->displayName().', '.$address);
    }

    /** Link that opens this branch in Google Maps (null when it has neither coordinates nor an address). */
    public function googleMapsUrl(): ?string
    {
        return ($q = $this->mapQuery()) ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($q) : null;
    }

    /** Link that starts Google Maps directions to this branch. */
    public function googleDirectionsUrl(): ?string
    {
        return ($q = $this->mapQuery()) ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($q) : null;
    }

    /** Embeddable Google Map (no API key needed) for an iframe. */
    public function googleEmbedUrl(): ?string
    {
        return ($q = $this->mapQuery()) ? 'https://maps.google.com/maps?q='.rawurlencode($q).'&z=15&output=embed' : null;
    }

    public function brandColor(): string
    {
        return $this->primary_color ?: ($this->institution?->primary_color ?? '#1f6f5c');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'bn' && $this->name_bn ? $this->name_bn : $this->name;
    }

    /** Private-disk folder for this branch's files. */
    public function storagePath(string $module = ''): string
    {
        return trim(config('tenancy.storage_root').'/'.$this->institution_id.'/'.$this->getKey().'/'.$module, '/');
    }
}
