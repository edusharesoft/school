<?php

namespace App\Models\Website;

use App\Models\Concerns\Auditable;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** A CMS page of the public website: institution-wide (branch_id null) or owned by one branch's site. */
class Page extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'website_pages';

    public const PLACEMENTS = ['menu', 'footer_quick', 'footer_campus', 'hidden'];

    protected $fillable = ['slug', 'title', 'title_bn', 'body', 'body_bn', 'meta_description', 'placement', 'sort_order', 'status'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'branch_id' => 'integer'];
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

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopePlacedIn(Builder $query, string $placement): Builder
    {
        return $query->where('placement', $placement)->orderBy('sort_order')->orderBy('title');
    }

    public function displayTitle(): string
    {
        return app()->getLocale() === 'bn' && $this->title_bn ? $this->title_bn : $this->title;
    }

    public function displayBody(): string
    {
        $body = app()->getLocale() === 'bn' && $this->body_bn ? $this->body_bn : (string) $this->body;

        return self::markdown($body);
    }

    /** Markdown → HTML with raw HTML stripped and unsafe links refused (no XSS through the CMS). */
    public static function markdown(string $text): string
    {
        return Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function url(): string
    {
        return $this->branch_id !== null ? route('site.page', $this->slug) : route('website.page', $this->slug);
    }
}
