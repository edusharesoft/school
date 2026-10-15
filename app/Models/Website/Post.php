<?php

namespace App\Models\Website;

use App\Models\Concerns\Auditable;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** News / blog article or public notice. */
class Post extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'website_posts';

    public const TYPES = ['news', 'notice'];

    protected $fillable = ['type', 'slug', 'title', 'title_bn', 'excerpt', 'body', 'body_bn', 'image_path', 'attachment_path', 'author_name', 'published_at', 'status'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'branch_id' => 'integer'];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** Content of the institution-wide site (null) or of one branch's own site. */
    public function scopeOfSite(Builder $query, ?int $branchId): Builder
    {
        return $branchId === null ? $query->whereNull('branch_id') : $query->where('branch_id', $branchId);
    }

    /** Published and already due (a future published_at keeps the post hidden — scheduled publishing). */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function displayTitle(): string
    {
        return app()->getLocale() === 'bn' && $this->title_bn ? $this->title_bn : $this->title;
    }

    public function displayBody(): string
    {
        $body = app()->getLocale() === 'bn' && $this->body_bn ? $this->body_bn : (string) $this->body;

        return Page::markdown($body);
    }

    public function route(): string
    {
        if ($this->branch_id !== null) {
            return $this->type === 'notice' ? route('site.notice', $this->slug) : route('site.post', $this->slug);
        }

        return $this->type === 'notice' ? route('website.notice', $this->slug) : route('website.post', $this->slug);
    }

    /** The list page this post belongs to (notices / news) on the site that owns it. */
    public function listRoute(): string
    {
        if ($this->branch_id !== null) {
            return $this->type === 'notice' ? route('site.notices') : route('site.news');
        }

        return $this->type === 'notice' ? route('website.notices') : route('website.news');
    }
}
