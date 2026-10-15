<?php

namespace App\Models\Learning;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * A branch's connection to Zoom or Google Meet. Credentials (config) and OAuth tokens are encrypted at rest and never
 * serialised: the settings screen only learns which secrets are present, never their values.
 */
class OnlineClassIntegration extends Model
{
    use Auditable, BelongsToBranch;

    public const PROVIDERS = ['zoom', 'google_meet'];

    protected $fillable = ['provider', 'is_enabled'];

    protected $hidden = ['config', 'tokens'];

    /** Never write secrets into the audit trail. */
    protected array $auditExclude = ['config', 'tokens'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean', 'config' => 'encrypted:array', 'tokens' => 'encrypted:array',
            'connected_at' => 'datetime', 'last_checked_at' => 'datetime',
            'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->config ?? [])[$key] ?? $default;
    }

    public function token(string $key, mixed $default = null): mixed
    {
        return ($this->tokens ?? [])[$key] ?? $default;
    }

    /** Merge new token values (e.g. a refreshed access token) and persist. */
    public function putTokens(array $values): void
    {
        $this->forceFill(['tokens' => array_merge($this->tokens ?? [], $values)])->save();
    }

    public function label(): string
    {
        return config("online_classes.providers.{$this->provider}.label", $this->provider);
    }
}
