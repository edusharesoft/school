<?php

namespace App\Http\Controllers\Branch\Website;

use App\Http\Controllers\Controller;
use App\Models\HR\Employee;
use App\Support\TenantRule;
use App\Services\Website\BranchSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Site settings of this branch's public website: hero, "Discover our School", messages, team, teachers, partners, footer. */
class SettingsController extends Controller
{
    public function __construct(protected BranchSite $site) {}

    public function edit(): View
    {
        $this->authorize('website.view');
        $branch = tenant()->branch();

        return view('branch.website.settings', [
            'branch' => $branch,
            'settings' => $this->site->settings($branch),
            'own' => $branch->settings['website'] ?? [],
            'icons' => config('website.feature_icons'),
            'employees' => Employee::query()->where('status', 'active')->with('designation:id,name,name_bn')->orderBy('name')->get(['id', 'name', 'designation_id', 'photo_path']),
            'limits' => ['messages' => BranchSite::MAX_MESSAGES, 'team' => BranchSite::MAX_TEAM, 'teachers' => BranchSite::MAX_TEACHERS],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('website.update');
        $branch = tenant()->branch();

        $person = fn (string $key, int $max, bool $text = false) => [
            $key => ['nullable', 'array', 'max:'.$max],
            "$key.*.name" => ['nullable', 'string', 'max:120'],
            "$key.*.designation" => ['nullable', 'string', 'max:120'],
            "$key.*.note" => ['nullable', 'string', 'max:160'],
            "$key.*.photo" => ['nullable', 'string', 'max:255'],
            "$key.*.remove_photo" => ['nullable', 'boolean'],
            "$key.*.employee_id" => ['nullable', 'integer', TenantRule::exists('employees', 'id', true)],
        ] + ($text ? ["$key.*.text" => ['nullable', 'string', 'max:6000']] : []);

        $image = fn (int $kb = 6144) => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.$kb];

        $data = $request->validate([
            'hero_image' => $image(),
            'remove_hero' => ['nullable', 'boolean'],
            'slides' => ['nullable', 'array', 'max:'.\App\Services\Website\HeroSlides::MAX],
            'slides.*.path' => ['required_with:slides', 'string', 'max:255'],
            'slides.*.title' => ['nullable', 'string', 'max:120'],
            'slides.*.text' => ['nullable', 'string', 'max:200'],
            'slides.*.link' => ['nullable', 'string', 'max:255', 'regex:#^(https?://|/(?!/))#i'],
            'slides.*.position' => ['nullable', 'integer', 'min:0', 'max:99'],
            'slides.*.remove' => ['nullable', 'boolean'],
            'hero_slides' => ['nullable', 'array', 'max:'.\App\Services\Website\HeroSlides::MAX],
            'hero_slides.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:6144'],
            'features' => ['nullable', 'array', 'max:3'],
            'features.*.icon' => ['nullable', Rule::in(array_keys(config('website.feature_icons')))],
            'features.*.title' => ['nullable', 'string', 'max:60'],
            'features.*.text' => ['nullable', 'string', 'max:200'],
            'features.*.tone' => ['nullable', Rule::in(['teal', 'orange'])],
            'about' => ['nullable', 'array'],
            'about.title' => ['nullable', 'string', 'max:120'],
            'about.subtitle' => ['nullable', 'string', 'max:200'],
            'about.text' => ['nullable', 'string', 'max:2000'],
            'about.years' => ['nullable', 'integer', 'min:0', 'max:500'],
            'about.years_label' => ['nullable', 'string', 'max:60'],
            'about.points' => ['nullable', 'array', 'max:3'],
            'about.points.*' => ['nullable', 'string', 'max:120'],
            'about.button_text' => ['nullable', 'string', 'max:40'],
            'about.button_url' => ['nullable', 'string', 'max:255', 'regex:#^(https?://|/)#i'],
            'about_image_main' => $image(),
            'about_image_second' => $image(),
            'remove_about_image_main' => ['nullable', 'boolean'],
            'remove_about_image_second' => ['nullable', 'boolean'],
            ...$person('messages', BranchSite::MAX_MESSAGES, true),
            ...$person('team', BranchSite::MAX_TEAM),
            ...$person('teachers', BranchSite::MAX_TEACHERS),
            'message_photos.*' => $image(4096),
            'team_photos.*' => $image(4096),
            'teacher_photos.*' => $image(4096),
            'partners' => ['nullable', 'array', 'max:12'],
            'partners.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_partners' => ['nullable', 'array'],
            'remove_partners.*' => ['string', 'max:255'],
            'social.*' => ['nullable', 'url:http,https', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:400'],
            'footer_text_bn' => ['nullable', 'string', 'max:400'],
        ]);

        $this->site->save($branch, $data, [
            'hero_image' => $request->file('hero_image'),
            'hero_slides' => array_values($request->file('hero_slides', [])),
            'about_image_main' => $request->file('about_image_main'),
            'about_image_second' => $request->file('about_image_second'),
            'message_photos' => $request->file('message_photos', []),
            'team_photos' => $request->file('team_photos', []),
            'teacher_photos' => $request->file('teacher_photos', []),
            'partners' => $request->file('partners', []),
        ], [
            'hero' => $request->boolean('remove_hero'),
            'about_image_main' => $request->boolean('remove_about_image_main'),
            'about_image_second' => $request->boolean('remove_about_image_second'),
        ]);

        return redirect()->route('branch.website.settings')->with('success', __('Website settings saved.'));
    }
}
