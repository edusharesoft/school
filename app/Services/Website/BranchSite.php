<?php

namespace App\Services\Website;

use App\Models\Branch;
use App\Models\HR\Employee;
use App\Models\Institution;
use App\Models\Website\Page;
use App\Models\Website\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Content of one branch's public website (its own address, e.g. dhaka.schooldomain.com).
 *
 * Defaults: config/website.php → 'branch'. What the branch administrator saves lives in
 * branches.settings->website and wins section by section. A branch without its own hero picture, partner
 * logos, social links or footer text shows those of the institution-wide site.
 */
class BranchSite
{
    public const MAX_MESSAGES = 2;

    public const MAX_TEAM = 6;

    public const MAX_TEACHERS = 8;

    public function __construct(protected SiteContent $central) {}

    /** @return array<string,mixed> */
    public function settings(Branch $branch): array
    {
        $defaults = config('website.branch');
        $saved = $branch->settings['website'] ?? [];

        foreach (is_array($saved) ? $saved : [] as $section => $value) {
            $default = $defaults[$section] ?? null;
            $defaults[$section] = (is_array($value) && is_array($default) && ! array_is_list($default))
                ? array_replace($default, $value)
                : $value;
        }

        // Fall back to the institution's site for the blocks every branch shares.
        $institution = $branch->institution ?? Institution::query()->find($branch->institution_id);
        $parent = $this->central->settings($institution);
        $defaults['hero']['slides'] = HeroSlides::effective($defaults['hero'] ?? []);
        if ($defaults['hero']['slides'] === []) {
            $defaults['hero']['slides'] = HeroSlides::effective($parent['hero'] ?? []);
            $defaults['hero']['image'] = $defaults['hero']['slides'][0]['image'] ?? null;
        }
        if (empty($defaults['partners'])) {
            $defaults['partners'] = $parent['partners'] ?? [];
        }
        if (! array_filter($defaults['social'] ?? [])) {
            $defaults['social'] = $parent['social'] ?? [];
        }
        if (empty($defaults['footer']['text']) && empty($defaults['footer']['text_bn'])) {
            $defaults['footer'] = $parent['footer'] ?? $defaults['footer'];
        }

        return $defaults;
    }

    /** Menu / footer pages of the branch site. @return array{menu:Collection,quick:Collection,campus:Collection} */
    public function navigation(Branch $branch): array
    {
        $pages = Page::query()->where('branch_id', $branch->id)->published()
            ->whereIn('placement', ['menu', 'footer_quick', 'footer_campus'])
            ->orderBy('sort_order')->orderBy('title')->get(['id', 'branch_id', 'slug', 'title', 'title_bn', 'placement', 'sort_order']);

        return [
            'menu' => $pages->where('placement', 'menu')->values(),
            'quick' => $pages->where('placement', 'footer_quick')->values(),
            'campus' => $pages->where('placement', 'footer_campus')->values(),
        ];
    }

    public function latestNews(Branch $branch, int $limit = 3): Collection
    {
        return Post::query()->where('branch_id', $branch->id)->ofType('news')->live()
            ->latest('published_at')->latest('id')->limit($limit)->get();
    }

    /** Starter pages (About Us, Academic, Regulations, Contact, footer links) — idempotent. */
    public function createDefaultPages(Branch $branch, ?int $userId = null): int
    {
        $made = 0;
        foreach (config('website.default_pages') as $def) {
            $exists = Page::withTrashed()->where('institution_id', $branch->institution_id)->where('slug', $def['slug'])->exists();
            if ($exists) {
                // The slug is unique per institution; a branch page gets a branch-specific slug.
                $slug = $def['slug'].'-'.$branch->id;
                if (Page::withTrashed()->where('institution_id', $branch->institution_id)->where('slug', $slug)->exists()) {
                    continue;
                }
            } else {
                $slug = $def['slug'];
            }
            if (Page::query()->where('branch_id', $branch->id)->where('title', $def['title'])->exists()) {
                continue;
            }
            $page = new Page(Arr::only($def, ['title', 'title_bn', 'placement', 'sort_order']) + [
                'slug' => $slug, 'status' => 'published',
                'body' => $def['title']."\n\n".__('This page has not been written yet. Edit it from Website → Pages.'),
            ]);
            $page->institution_id = $branch->institution_id;
            $page->branch_id = $branch->id;
            $page->created_by = $userId;
            $page->save();
            $made++;
        }

        return $made;
    }

    // ------------------------------------------------------------------ writes (branch administrator)

    /**
     * Save the site settings of a branch.
     *
     * @param  array<string,mixed>  $data  validated input
     * @param  array<string,mixed>  $files  uploaded files keyed like the form (hero_image, about_image_main, message_photos[], team_photos[] …)
     */
    public function save(Branch $branch, array $data, array $files, array $remove = []): void
    {
        $settings = $branch->settings ?? [];
        $web = $settings['website'] ?? [];
        $dir = 'website/branch-'.$branch->id;

        // Hero picture + feature boxes
        $hero = $web['hero'] ?? [];
        $old = HeroSlides::effective($hero);
        if (! empty($remove['hero'])) {
            $data['slides'] = [];
        }
        $uploads = array_values(array_filter([...($files['hero_slides'] ?? []), $files['hero_image'] ?? null]));
        $slides = (new HeroSlides)->build($old, array_key_exists('slides', $data) ? (array) $data['slides'] : null, $uploads, $dir.'/hero', fn ($p) => $this->forget($p, $branch));
        $hero = HeroSlides::store($hero, $slides);
        $hero['features'] = collect($data['features'] ?? [])
            ->map(fn ($f) => [
                'icon' => $f['icon'] ?? 'fa-star',
                'title' => trim((string) ($f['title'] ?? '')),
                'text' => trim((string) ($f['text'] ?? '')),
                'tone' => in_array($f['tone'] ?? 'teal', ['teal', 'orange'], true) ? $f['tone'] : 'teal',
            ])->filter(fn ($f) => $f['title'] !== '')->values()->all();
        $web['hero'] = $hero;

        // "Discover our School"
        $about = $web['about'] ?? [];
        $in = $data['about'] ?? [];
        foreach (['title', 'subtitle', 'text', 'years_label', 'button_text', 'button_url'] as $k) {
            $about[$k] = trim((string) ($in[$k] ?? '')) ?: null;
        }
        $about['years'] = isset($in['years']) && $in['years'] !== '' ? (int) $in['years'] : null;
        $about['points'] = collect($in['points'] ?? [])->map(fn ($p) => trim((string) $p))->filter()->take(3)->values()->all();
        $about['image_main'] = $this->replace($about['image_main'] ?? null, $files['about_image_main'] ?? null, ! empty($remove['about_image_main']), $dir.'/about', $branch);
        $about['image_second'] = $this->replace($about['image_second'] ?? null, $files['about_image_second'] ?? null, ! empty($remove['about_image_second']), $dir.'/about', $branch);
        $web['about'] = $about;

        // Messages, team, teachers
        $web['messages'] = ['items' => $this->people($branch, $data['messages'] ?? [], $files['message_photos'] ?? [], $web['messages']['items'] ?? [], $dir.'/people', self::MAX_MESSAGES, true)];
        $web['team'] = ['items' => $this->people($branch, $data['team'] ?? [], $files['team_photos'] ?? [], $web['team']['items'] ?? [], $dir.'/people', self::MAX_TEAM)];
        $web['teachers'] = ['items' => $this->people($branch, $data['teachers'] ?? [], $files['teacher_photos'] ?? [], $web['teachers']['items'] ?? [], $dir.'/people', self::MAX_TEACHERS)];

        // Partner logos
        $removePartners = $data['remove_partners'] ?? [];
        $old = $web['partners'] ?? [];
        $partners = array_values(array_diff($old, $removePartners));
        foreach (array_intersect($removePartners, $old) as $path) {
            $this->forget($path, $branch);
        }
        foreach ($files['partners'] ?? [] as $file) {
            $partners[] = $file->store($dir.'/partners', 'public');
        }
        $web['partners'] = array_slice($partners, 0, 12);

        $web['social'] = Arr::only(array_map(fn ($v) => $v ?: null, $data['social'] ?? []), ['facebook', 'youtube', 'instagram', 'linkedin']);
        $web['footer'] = ['text' => trim((string) ($data['footer_text'] ?? '')) ?: null, 'text_bn' => trim((string) ($data['footer_text_bn'] ?? '')) ?: null];

        $settings['website'] = $web;
        $branch->forceFill(['settings' => $settings])->save();
    }

    /**
     * Rebuild a list of people / messages from the form. Existing photos are kept only when the path really
     * belongs to the saved list (never trust a path from the browser); photos of dropped rows are deleted.
     *
     * @param  array<int|string,array<string,mixed>>  $rows
     * @param  array<int|string,UploadedFile>  $files
     * @param  array<int,array<string,mixed>>  $old
     */
    protected function people(Branch $branch, array $rows, array $files, array $old, string $dir, int $max, bool $withText = false): array
    {
        $oldPhotos = array_filter(Arr::pluck($old, 'photo'));
        $out = [];

        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || count($out) >= $max) {
                continue;
            }
            $photo = $row['photo'] ?? null;
            $photo = in_array($photo, $oldPhotos, true) ? $photo : null;

            if (! empty($row['remove_photo'])) {
                $photo = null;
            }
            if (($file = $files[$i] ?? null) instanceof UploadedFile) {
                $photo = $file->store($dir, 'public');
            } elseif (! $photo && ! empty($row['employee_id'])) {
                $photo = $this->copyEmployeePhoto((int) $row['employee_id'], $dir);
            }

            $item = [
                'name' => $name,
                'designation' => trim((string) ($row['designation'] ?? '')) ?: null,
                'note' => trim((string) ($row['note'] ?? '')) ?: null,
                'photo' => $photo,
            ];
            if ($withText) {
                $item['text'] = trim((string) ($row['text'] ?? '')) ?: null;
            }
            $out[] = $item;
        }

        $kept = array_filter(Arr::pluck($out, 'photo'));
        foreach (array_diff($oldPhotos, $kept) as $gone) {
            $this->forget($gone, $branch);
        }

        return $out;
    }

    /** A private employee photo is copied to the public disk only because the administrator chose that employee for the site. */
    protected function copyEmployeePhoto(int $employeeId, string $dir): ?string
    {
        $employee = Employee::query()->find($employeeId); // branch scope: only this branch's employees
        if (! $employee?->photo_path || ! Storage::disk('local')->exists($employee->photo_path)) {
            return null;
        }
        $ext = strtolower(pathinfo($employee->photo_path, PATHINFO_EXTENSION)) ?: 'jpg';
        $target = $dir.'/emp-'.$employee->id.'-'.Str::lower(Str::random(6)).'.'.$ext;
        Storage::disk('public')->put($target, Storage::disk('local')->get($employee->photo_path));

        return $target;
    }

    protected function replace(?string $current, ?UploadedFile $file, bool $remove, string $dir, Branch $branch): ?string
    {
        if ($file) {
            $this->forget($current, $branch);

            return $file->store($dir, 'public');
        }
        if ($remove) {
            $this->forget($current, $branch);

            return null;
        }

        return $current;
    }

    /** Only files this branch uploaded for its site are ever deleted. */
    protected function forget(?string $path, Branch $branch): void
    {
        if ($path && str_starts_with($path, 'website/branch-'.$branch->id.'/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
