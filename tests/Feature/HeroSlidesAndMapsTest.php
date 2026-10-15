<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institution;
use App\Models\User;
use App\Services\Website\HeroSlides;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Banner slides in the hero of the main and branch websites, and the Google Map of "All Branch Locations". */
class HeroSlidesAndMapsTest extends TestCase
{
    use RefreshDatabase;

    protected function world(): array
    {
        $institution = Institution::factory()->create(['status' => 'active']);
        $branch = $this->makeBranch($institution, ['name' => 'Dhaka Campus', 'address' => 'Tejgaon, Dhaka', 'latitude' => 23.76, 'longitude' => 90.39]);
        $other = $this->makeBranch($institution, ['name' => 'Chattogram Campus', 'address' => 'Agrabad, Chattogram', 'latitude' => 22.35, 'longitude' => 91.78]);
        $addressOnly = $this->makeBranch($institution, ['name' => 'Sylhet Campus', 'address' => 'Zindabazar, Sylhet']);
        $foreign = $this->makeBranch(Institution::factory()->create(['status' => 'active']), ['name' => 'Foreign Campus', 'address' => 'Elsewhere']);
        $admin = $this->branchUser($branch, 'branch-admin');

        return compact('institution', 'branch', 'other', 'addressOnly', 'foreign', 'admin');
    }

    protected function img(string $name = 'x.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 400);
    }

    protected function save(array $w, array $payload)
    {
        return $this->actingOnBranch($w['admin'], $w['branch'])->put($this->branchUrl($w['branch'], 'website/settings'), $payload);
    }

    public function test_branch_admin_uploads_several_slides_and_the_public_site_slides_them(): void
    {
        Storage::fake('public');
        $w = $this->world();

        $this->save($w, ['hero_slides' => [$this->img('a.jpg'), $this->img('b.jpg'), $this->img('c.jpg')]])->assertSessionHasNoErrors();

        $hero = $w['branch']->fresh()->settings['website']['hero'];
        $this->assertCount(3, $hero['slides']);
        $this->assertSame($hero['slides'][0]['image'], $hero['image'], 'the first slide mirrors the single hero picture');
        foreach ($hero['slides'] as $s) {
            $this->assertStringStartsWith('website/branch-'.$w['branch']->id.'/hero', $s['image']);
            Storage::disk('public')->assertExists($s['image']);
        }

        $html = $this->get($this->branchUrl($w['branch'], '/'))->assertOk()->assertSee('data-hero-slider', false)->assertSee('data-hero-next', false)->getContent();
        $this->assertSame(3, substr_count($html, 'class="hero-slide'));
        $this->assertSame(3, substr_count($html, 'data-hero-dot='));
    }

    public function test_one_slide_has_no_controls_and_no_slide_shows_the_plain_banner(): void
    {
        Storage::fake('public');
        $w = $this->world();

        $this->get($this->branchUrl($w['branch'], '/'))->assertOk()->assertDontSee('hero-slide', false)->assertSee('site-hero-text', false);

        $this->save($w, ['hero_image' => $this->img()])->assertSessionHasNoErrors();
        $this->get($this->branchUrl($w['branch'], '/'))->assertOk()->assertSee('hero-slide', false)->assertDontSee('data-hero-slider', false)->assertDontSee('data-hero-next', false);
    }

    public function test_captions_order_removal_and_unsafe_links(): void
    {
        Storage::fake('public');
        $w = $this->world();
        $this->save($w, ['hero_slides' => [$this->img('a.jpg'), $this->img('b.jpg'), $this->img('c.jpg')]]);
        $slides = $w['branch']->fresh()->settings['website']['hero']['slides'];
        [$a, $b, $c] = array_column($slides, 'image');

        // reorder (c first), caption on b, drop a
        $this->save($w, ['slides' => [
            ['path' => $a, 'position' => 1, 'remove' => '1'],
            ['path' => $b, 'position' => 2, 'title' => 'Admission open', 'text' => 'Apply today', 'link' => '/admission'],
            ['path' => $c, 'position' => 0],
        ]])->assertSessionHasNoErrors();

        $hero = $w['branch']->fresh()->settings['website']['hero'];
        $this->assertSame([$c, $b], array_column($hero['slides'], 'image'));
        $this->assertSame($c, $hero['image']);
        Storage::disk('public')->assertMissing($a);
        Storage::disk('public')->assertExists($b);
        $this->get($this->branchUrl($w['branch'], '/'))->assertSee('Admission open')->assertSee('Apply today')->assertSee('href="/admission"', false);

        foreach (['javascript:alert(1)', '//evil.example.com/x'] as $bad) {
            $this->save($w, ['slides' => [['path' => $b, 'link' => $bad]]])->assertSessionHasErrors('slides.0.link');
        }
    }

    public function test_a_path_from_the_browser_that_is_not_a_slide_is_ignored_and_a_save_without_slides_keeps_them(): void
    {
        Storage::fake('public');
        $w = $this->world();
        Storage::disk('public')->put('website/branch-999/hero/secret.jpg', 'x');
        $this->save($w, ['hero_slides' => [$this->img('a.jpg')]]);
        $mine = $w['branch']->fresh()->settings['website']['hero']['slides'][0]['image'];

        $this->save($w, ['footer_text' => 'Hello']);   // no "slides" field at all
        $this->assertSame([$mine], array_column($w['branch']->fresh()->settings['website']['hero']['slides'], 'image'));

        $this->save($w, ['slides' => [['path' => $mine], ['path' => 'website/branch-999/hero/secret.jpg']]]);
        $this->assertSame([$mine], array_column($w['branch']->fresh()->settings['website']['hero']['slides'], 'image'));
        Storage::disk('public')->assertExists('website/branch-999/hero/secret.jpg');
    }

    public function test_at_most_eight_slides(): void
    {
        Storage::fake('public');
        $w = $this->world();
        $files = array_map(fn ($i) => $this->img("s$i.jpg"), range(1, HeroSlides::MAX + 1));

        $this->save($w, ['hero_slides' => $files])->assertSessionHasErrors('hero_slides');

        $this->save($w, ['hero_slides' => array_slice($files, 0, HeroSlides::MAX)])->assertSessionHasNoErrors();
        $this->assertCount(HeroSlides::MAX, $w['branch']->fresh()->settings['website']['hero']['slides']);
        $this->save($w, ['hero_slides' => [$this->img('extra.jpg')]])->assertSessionHasNoErrors();
        $this->assertCount(HeroSlides::MAX, $w['branch']->fresh()->settings['website']['hero']['slides']);
    }

    public function test_a_branch_without_slides_shows_the_institution_slides(): void
    {
        Storage::fake('public');
        $w = $this->world();
        $w['institution']->forceFill(['settings' => ['website' => ['hero' => ['slides' => [
            ['image' => 'https://img.example.com/one.jpg', 'title' => 'Institution banner'],
            ['image' => 'https://img.example.com/two.jpg'],
        ]]]]])->save();

        $this->get($this->branchUrl($w['branch'], '/'))->assertOk()->assertSee('data-hero-slider', false)
            ->assertSee('https://img.example.com/one.jpg', false)->assertSee('https://img.example.com/two.jpg', false)->assertSee('Institution banner');
    }

    public function test_the_old_single_hero_picture_still_works_and_is_editable_as_a_slide(): void
    {
        Storage::fake('public');
        $w = $this->world();
        Storage::disk('public')->put('website/branch-'.$w['branch']->id.'/hero/old.jpg', 'x');
        $w['branch']->forceFill(['settings' => ['website' => ['hero' => ['image' => 'website/branch-'.$w['branch']->id.'/hero/old.jpg']]]])->save();

        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], 'website/settings'))->assertOk()->assertSee('old.jpg', false);
        $this->get($this->branchUrl($w['branch'], '/'))->assertOk()->assertSee('old.jpg', false);

        $this->save($w, ['hero_slides' => [$this->img('new.jpg')]]);
        $this->assertCount(2, $w['branch']->fresh()->settings['website']['hero']['slides']);
    }

    public function test_main_website_slides_are_managed_from_super_admin(): void
    {
        Storage::fake('public');
        $institution = Institution::factory()->create(['status' => 'active']);
        $root = User::factory()->platform()->role('super-admin')->create();

        $this->actingCentral($root)->get($this->centralUrl('super-admin/website/settings'))->assertOk()->assertSee('Hero banner slides')->assertSee('hero_slides[]', false);

        $this->actingCentral($root)->put($this->centralUrl('super-admin/website/settings'), [
            'hero_slides' => [$this->img('a.jpg'), $this->img('b.jpg')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $hero = $institution->fresh()->settings['website']['hero'];
        $this->assertCount(2, $hero['slides']);
        foreach ($hero['slides'] as $s) {
            Storage::disk('public')->assertExists($s['image']);
        }
        $this->get($this->centralUrl('/'))->assertOk()->assertSee('data-hero-slider', false);

        $this->actingCentral($root)->put($this->centralUrl('super-admin/website/settings'), ['remove_hero' => '1'])->assertRedirect();
        $this->assertSame([], $institution->fresh()->settings['website']['hero']['slides']);
        Storage::disk('public')->assertMissing($hero['slides'][0]['image']);
    }

    public function test_branch_site_lists_every_branch_with_google_map_and_links(): void
    {
        $w = $this->world();

        $html = $this->get($this->branchUrl($w['branch'], '/'))->assertOk()
            ->assertSee('All Branch Locations')->assertSee('Google Maps')->assertSee('Directions')
            ->assertSee('Chattogram Campus')->assertSee('Sylhet Campus')->assertDontSee('Foreign Campus')
            ->getContent();

        // the Google embed starts on the branch being viewed
        $this->assertStringContainsString('src="https://maps.google.com/maps?q=23.76%2C90.39&amp;z=15&amp;output=embed"', $html);
        // a branch with coordinates links by pin, one with only an address by its text
        $this->assertStringContainsString('https://www.google.com/maps/search/?api=1&amp;query=22.35%2C91.78', $html);
        $this->assertStringContainsString('https://www.google.com/maps/dir/?api=1&amp;destination=22.35%2C91.78', $html);
        $this->assertStringContainsString(rawurlencode('Sylhet Campus, Zindabazar, Sylhet'), $html);
        $this->assertSame(3, substr_count($html, 'class="loc-item'));
        // the all-pins overview map is offered because several branches have a pin
        $this->assertStringContainsString('data-overview', $html);
    }

    public function test_branch_google_links(): void
    {
        $i = Institution::factory()->create(['status' => 'active']);
        $pin = $this->makeBranch($i, ['name' => 'Pin', 'latitude' => 23.5, 'longitude' => 90.5, 'address' => 'x']);
        $text = $this->makeBranch($i, ['name' => 'Text', 'address' => 'Road 5, Dhaka']);
        $none = $this->makeBranch($i, ['name' => 'None', 'address' => null]);

        $this->assertSame('https://www.google.com/maps/search/?api=1&query=23.5%2C90.5', $pin->googleMapsUrl());
        $this->assertSame('https://maps.google.com/maps?q=23.5%2C90.5&z=15&output=embed', $pin->googleEmbedUrl());
        $this->assertStringContainsString(rawurlencode('Text, Road 5, Dhaka'), $text->googleDirectionsUrl());
        $this->assertNull($none->googleMapsUrl());
        $this->assertNull($none->googleEmbedUrl());
    }

    public function test_main_website_shows_the_same_location_list(): void
    {
        $w = $this->world();

        $this->get($this->centralUrl('/'))->assertOk()->assertSee('data-locations', false)->assertSee('Sylhet Campus')->assertSee('Directions');
        $this->get($this->centralUrl('/branches'))->assertOk()->assertSee('data-locations', false);
    }
}
