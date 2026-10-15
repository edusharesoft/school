<?php

namespace App\Models\Communication;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Notice board entry. Who may see it is decided by NoticeAudience (audience + audience_filter);
 * visibility in time by status, publish_at and expires_at.
 */
class Notice extends Model
{
    use Auditable, BelongsToBranch;

    public const CATEGORIES = ['general', 'academic', 'exam', 'holiday', 'event', 'meeting', 'fee', 'emergency'];

    public const AUDIENCES = ['all', 'staff', 'teachers', 'students', 'guardians', 'classes', 'students_specific'];

    protected $fillable = [
        'title', 'body', 'category', 'audience', 'audience_filter', 'is_emergency', 'is_pinned', 'is_public', 'channels',
        'publish_at', 'expires_at', 'attachment_path', 'attachment_name',
    ];

    protected $guarded = ['status', 'published_at', 'published_by', 'distributed_at', 'group_uuid', 'created_by'];

    protected function casts(): array
    {
        return [
            'audience_filter' => 'array', 'channels' => 'array', 'is_emergency' => 'boolean', 'is_pinned' => 'boolean', 'is_public' => 'boolean',
            'publish_at' => 'datetime', 'expires_at' => 'datetime', 'published_at' => 'datetime', 'distributed_at' => 'datetime',
            'published_by' => 'integer', 'created_by' => 'integer', 'branch_id' => 'integer', 'institution_id' => 'integer',
        ];
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** Published, already live and not expired. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isLive(): bool
    {
        return $this->status === 'published' && (! $this->publish_at || $this->publish_at->lte(now())) && (! $this->expires_at || $this->expires_at->gt(now()));
    }

    public function excerpt(int $length = 120): string
    {
        return \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', $this->body)), $length);
    }

    public function tone(): string
    {
        return match ($this->status) {
            'published' => $this->isLive() ? 'success' : 'secondary', 'scheduled' => 'info', 'draft' => 'warning', default => 'secondary',
        };
    }

    public function displayStatus(): string
    {
        if ($this->status === 'published' && $this->expires_at && $this->expires_at->lte(now())) {
            return 'expired';
        }

        return $this->status;
    }
}
