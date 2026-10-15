<?php

namespace App\Services\Website;

use App\Models\Branch;
use App\Models\Institution;
use App\Models\Website\Page;
use App\Models\Website\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Everything the public website needs to render, plus the writes of the "Website settings" screen.
 * Defaults come from config/website.php; whatever an administrator saves lives in
 * institutions.settings->website and wins section by section.
 */
class SiteContent
{
    private ?Institution $institution = null;

    private bool $resolved = false;

    /** The institution the public website speaks for (the first active one). */
    public function institution(): ?Institution
    {
        if (! $this->resolved) {
            $this->institution = Institution::query()->where('status', 'active')->orderBy('id')->first();
            $this->resolved = true;
        }

        return $this->institution;
    }

    /** Institution the signed-in administrator manages (platform owner: the public site's institution). */
    public function forAdmin(?\App\Models\User $user): ?Institution
    {
        // On a branch host the content always belongs to that branch's institution.
        if ($branch = app(\App\Tenancy\TenantContext::class)->branch()) {
            return $branch->institution ?? Institution::query()->find($branch->institution_id);
        }

        if ($user && ! $user->isPlatformOwner() && $user->institution_id) {
            return Institution::query()->find($user->institution_id);
        }

        return $this->institution();
    }

    /** @return array<string,mixed> */
    public function settings(?Institution $institution = null): array
    {
        $institution ??= $this->institution();
        $defaults = config('website');
        $saved = $institution?->settings['website'] ?? [];

        foreach (is_array($saved) ? $saved : [] as $section => $value) {
            $default = $defaults[$section] ?? null;
            $defaults[$section] = (is_array($value) && is_array($default) && ! array_is_list($default))
                ? array_replace($default, $value)
                : $value;
        }

        $defaults['hero']['slides'] = HeroSlides::effective($defaults['hero'] ?? []);

        return $defaults;
    }

    /** Active branches for the photo grid and the map. */
    public function branches(): Collection
    {
        return Branch::query()->active()
            ->whereHas('institution', fn ($q) => $q->where('status', 'active'))
            ->with(['institution:id,name', 'domains'])
            ->ordered()
            ->get(['id', 'institution_id', 'name', 'name_bn', 'subdomain', 'type', 'address', 'phone', 'email',
                'logo_path', 'cover_path', 'latitude', 'longitude', 'primary_color']);
    }

    /** Navigation, footer link lists. */
    public function navigation(?Institution $institution = null): array
    {
        $institution ??= $this->institution();
        if (! $institution) {
            return ['menu' => collect(), 'quick' => collect(), 'campus' => collect()];
        }

        $pages = Page::query()->where('institution_id', $institution->id)->published()
            ->whereNull('branch_id')->whereIn('placement', ['menu', 'footer_quick', 'footer_campus'])
            ->orderBy('sort_order')->orderBy('title')->get(['id', 'slug', 'title', 'title_bn', 'placement', 'sort_order']);

        return [
            'menu' => $pages->where('placement', 'menu')->values(),
            'quick' => $pages->where('placement', 'footer_quick')->values(),
            'campus' => $pages->where('placement', 'footer_campus')->values(),
        ];
    }

    public function latestNews(?Institution $institution = null, int $limit = 3): Collection
    {
        $institution ??= $this->institution();

        return $institution
            ? Post::query()->where('institution_id', $institution->id)->whereNull('branch_id')->ofType('news')->live()->latest('published_at')->latest('id')->limit($limit)->get()
            : collect();
    }

    // ------------------------------------------------------------------ writes (admin)

    /**
     * Save the website settings of an institution.
     *
     * @param  array<string,mixed>  $data  validated input (hero_features, social, footer_text …)
     * @param  array<int,UploadedFile>  $newPartners
     * @param  array<int,string>  $removePartners  stored paths to drop
     */
    public function saveSettings(Institution $institution, array $data, ?UploadedFile $heroImage, array $newPartners, array $removePartners, bool $removeHero = false, array $slideUploads = []): void
    {
        $settings = $institution->settings ?? [];
        $web = $settings['website'] ?? [];

        // Hero banner slides (a single `hero_image` upload is simply one more slide)
        $hero = $web['hero'] ?? [];
        $old = HeroSlides::effective($hero);
        if ($removeHero) {
            $data['slides'] = [];
        }
        $uploads = array_values(array_filter([...$slideUploads, $heroImage]));
        $slides = (new HeroSlides)->build($old, array_key_exists('slides', $data) ? (array) $data['slides'] : null, $uploads, 'website/hero', fn ($p) => $this->deleteStored($p));
        $hero = HeroSlides::store($hero, $slides);
        $hero['features'] = collect($data['features'] ?? [])
            ->map(fn ($f) => [
                'icon' => $f['icon'] ?? 'fa-star',
                'title' => trim((string) ($f['title'] ?? '')),
                'text' => trim((string) ($f['text'] ?? '')),
                'tone' => in_array($f['tone'] ?? 'teal', ['teal', 'orange'], true) ? $f['tone'] : 'teal',
            ])
            ->filter(fn ($f) => $f['title'] !== '')
            ->values()->all();
        $web['hero'] = $hero;

        // Partner / affiliation logos
        $partners = array_values(array_diff($web['partners'] ?? [], $removePartners));
        foreach ($removePartners as $path) {
            if (in_array($path, $web['partners'] ?? [], true)) {
                $this->deleteStored($path);
            }
        }
        foreach ($newPartners as $file) {
            $partners[] = $file->store('website/partners', 'public');
        }
        $web['partners'] = $partners;

        $web['social'] = Arr::only(array_map(fn ($v) => $v ?: null, $data['social'] ?? []), ['facebook', 'youtube', 'instagram', 'linkedin']);
        $web['footer'] = ['text' => trim((string) ($data['footer_text'] ?? '')) ?: null, 'text_bn' => trim((string) ($data['footer_text_bn'] ?? '')) ?: null];

        $settings['website'] = $web;
        $institution->forceFill(['settings' => $settings])->save();
    }

    protected function deleteStored(?string $path): void
    {
        if ($path && str_starts_with($path, 'website/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
