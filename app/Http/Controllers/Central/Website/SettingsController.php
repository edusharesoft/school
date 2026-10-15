<?php

namespace App\Http\Controllers\Central\Website;

use App\Http\Controllers\Controller;
use App\Services\Website\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Home page blocks: hero picture, the three feature boxes, partner logos, social links, footer text. */
class SettingsController extends Controller
{
    public function __construct(protected SiteContent $site) {}

    public function edit(Request $request): View
    {
        $this->authorize('website.view');
        $institution = $this->site->forAdmin($request->user());
        abort_unless($institution, 404);

        return view('central.website.settings', [
            'institution' => $institution,
            'settings' => $this->site->settings($institution),
            'icons' => config('website.feature_icons'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('website.update');
        $institution = $this->site->forAdmin($request->user());
        abort_unless($institution, 404);

        $data = $request->validate([
            'features' => ['nullable', 'array', 'max:3'],
            'features.*.icon' => ['nullable', Rule::in(array_keys(config('website.feature_icons')))],
            'features.*.title' => ['nullable', 'string', 'max:60'],
            'features.*.text' => ['nullable', 'string', 'max:200'],
            'features.*.tone' => ['nullable', Rule::in(['teal', 'orange'])],
            'social.*' => ['nullable', 'url:http,https', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:400'],
            'footer_text_bn' => ['nullable', 'string', 'max:400'],
            'hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:6144'],
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
            'partners' => ['nullable', 'array', 'max:12'],
            'partners.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_partners' => ['nullable', 'array'],
            'remove_partners.*' => ['string', 'max:255'],
        ]);

        $this->site->saveSettings(
            $institution,
            $data,
            $request->file('hero_image'),
            $request->file('partners', []),
            $data['remove_partners'] ?? [],
            $request->boolean('remove_hero'),
            array_values($request->file('hero_slides', [])),
        );

        return redirect()->route('central.website.settings')->with('success', __('Website settings saved.'));
    }
}
