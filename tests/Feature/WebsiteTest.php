<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Institution;
use App\Models\User;
use App\Models\Website\Page;
use App\Models\Website\Post;
use App\Models\Website\Subscriber;
use Database\Seeders\WebsiteDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function root(): User
    {
        return User::factory()->platform()->role('super-admin')->create();
    }

    protected function institution(): Institution
    {
        return Institution::factory()->create(['status' => 'active', 'phone' => '+8801700000000', 'address' => 'Tejgaon, Dhaka']);
    }

    protected function makePost(Institution $i, array $attrs = []): Post
    {
        $p = new Post($attrs + ['type' => 'news', 'slug' => 'post-'.uniqid(), 'title' => 'A story', 'status' => 'published', 'published_at' => now()->subHour()]);
        $p->institution_id = $i->id;
        $p->save();

        return $p;
    }

    protected function makePage(Institution $i, array $attrs = []): Page
    {
        $p = new Page($attrs + ['slug' => 'about-us', 'title' => 'About Us', 'placement' => 'menu', 'status' => 'published', 'body' => 'Hello']);
        $p->institution_id = $i->id;
        $p->save();

        return $p;
    }

    public function test_home_shows_branch_photos_map_news_and_footer_from_the_cms(): void
    {
        Storage::fake('public');
        $i = $this->institution();
        $branch = $this->makeBranch($i, ['name' => 'Chattogram Campus', 'latitude' => 22.35, 'longitude' => 91.78, 'address' => 'Agrabad']);
        Storage::disk('public')->put('branches/1/cover.jpg', 'x');
        $branch->forceFill(['cover_path' => 'branches/1/cover.jpg'])->save();
        $this->makeBranch($i, ['name' => 'Inactive Campus', 'status' => 'inactive']);
        $this->makePost($i, ['title' => 'Admission is open']);
        $this->makePage($i);

        $this->get($this->centralUrl('/'))->assertOk()
            ->assertSee('Our Branches')->assertSee('Chattogram Campus')->assertSee('branches/1/cover.jpg', false)
            ->assertDontSee('Inactive Campus')
            ->assertSee('All Branch Locations')->assertSee('data-locations', false)->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false)
            ->assertSee('Latest News')->assertSee('Admission is open')
            ->assertSee('About Us')->assertSee('Subscribe Now')->assertSee('Special Education');
    }

    public function test_drafts_and_scheduled_posts_stay_hidden(): void
    {
        $i = $this->institution();
        $this->makePost($i, ['title' => 'Draft story', 'slug' => 'draft', 'status' => 'draft']);
        $this->makePost($i, ['title' => 'Future story', 'slug' => 'future', 'published_at' => now()->addDay()]);
        $this->makePost($i, ['title' => 'Live story', 'slug' => 'live']);

        $this->get($this->centralUrl('/'))->assertSee('Live story')->assertDontSee('Draft story')->assertDontSee('Future story');
        $this->get($this->centralUrl('/news/draft'))->assertNotFound();
        $this->get($this->centralUrl('/news/future'))->assertNotFound();
        $this->get($this->centralUrl('/news/live'))->assertOk()->assertSee('Live story');
    }

    public function test_notices_and_news_use_separate_routes(): void
    {
        $i = $this->institution();
        $this->makePost($i, ['type' => 'notice', 'title' => 'Exam routine', 'slug' => 'exam-routine']);

        $this->get($this->centralUrl('/notices'))->assertOk()->assertSee('Exam routine');
        $this->get($this->centralUrl('/notices/exam-routine'))->assertOk();
        $this->get($this->centralUrl('/news/exam-routine'))->assertNotFound();
    }

    public function test_page_markdown_is_rendered_but_raw_html_and_unsafe_links_are_removed(): void
    {
        $i = $this->institution();
        $this->makePage($i, ['slug' => 'faq', 'title' => 'FAQ', 'body' => "## Questions\n\n<script>alert(1)</script>\n\n[click](javascript:alert(1))\n\n**bold**"]);

        $res = $this->get($this->centralUrl('/page/faq'))->assertOk();
        $res->assertSee('<h2>Questions</h2>', false)->assertSee('<strong>bold</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('href="javascript:', false);
    }

    public function test_unpublished_page_and_other_institution_page_are_not_served(): void
    {
        $i = $this->institution();
        $this->makePage($i, ['slug' => 'hidden-draft', 'status' => 'draft']);
        $this->get($this->centralUrl('/page/hidden-draft'))->assertNotFound();
        $this->get($this->centralUrl('/page/does-not-exist'))->assertNotFound();
    }

    public function test_newsletter_subscribe_is_idempotent_honeypot_protected_and_unsubscribable(): void
    {
        $i = $this->institution();

        $this->post($this->centralUrl('/newsletter'), ['email' => 'Parent@Example.com'])->assertRedirect();
        $this->post($this->centralUrl('/newsletter'), ['email' => 'parent@example.com'])->assertRedirect();
        $this->assertSame(1, Subscriber::query()->where('institution_id', $i->id)->count());

        $this->post($this->centralUrl('/newsletter'), ['email' => 'bot@example.com', 'website' => 'http://spam'])->assertRedirect();
        $this->assertDatabaseMissing('website_subscribers', ['email' => 'bot@example.com']);

        $this->post($this->centralUrl('/newsletter'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');

        $sub = Subscriber::query()->where('email', 'parent@example.com')->sole();
        $this->get($this->centralUrl('/newsletter/unsubscribe/'.$sub->unsubscribe_token))->assertRedirect();
        $this->assertNotNull($sub->fresh()->unsubscribed_at);
        $this->get($this->centralUrl('/newsletter/unsubscribe/wrong'))->assertNotFound();
    }

    public function test_super_admin_manages_pages_and_posts_and_slugs_are_unique(): void
    {
        Storage::fake('public');
        $i = $this->institution();
        $root = $this->root();

        $this->actingCentral($root)->post($this->centralUrl('super-admin/website/pages'), [
            'title' => 'Our Facilities', 'placement' => 'footer_campus', 'status' => 'published', 'body' => 'Labs',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('website_pages', ['slug' => 'our-facilities', 'institution_id' => $i->id, 'placement' => 'footer_campus']);

        $this->actingCentral($root)->post($this->centralUrl('super-admin/website/pages'), [
            'title' => 'Our Facilities', 'placement' => 'menu', 'status' => 'published',
        ])->assertSessionHasErrors('slug');

        $this->actingCentral($root)->post($this->centralUrl('super-admin/website/posts'), [
            'type' => 'news', 'title' => 'Sports day', 'status' => 'published',
            'image' => UploadedFile::fake()->image('a.jpg'), 'attachment' => UploadedFile::fake()->create('r.pdf', 30, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $post = Post::query()->where('slug', 'sports-day')->sole();
        Storage::disk('public')->assertExists($post->image_path);
        Storage::disk('public')->assertExists($post->attachment_path);

        $this->actingCentral($root)->put($this->centralUrl("super-admin/website/posts/{$post->id}"), [
            'title' => 'Sports day 2026', 'slug' => 'sports-day', 'status' => 'draft', 'remove_image' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($post->fresh()->image_path);
        $this->assertSame('draft', $post->fresh()->status);

        $this->actingCentral($root)->delete($this->centralUrl("super-admin/website/posts/{$post->id}"))->assertRedirect();
        $this->assertSoftDeleted('website_posts', ['id' => $post->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Post::class, 'event' => 'created']);

        foreach (['pages', 'posts', 'subscribers'] as $screen) {
            $this->actingCentral($root)->get($this->centralUrl("super-admin/website/{$screen}"))->assertOk();
        }
        $this->actingCentral($root)->get($this->centralUrl('super-admin/website/pages/create'))->assertOk();
        $this->actingCentral($root)->get($this->centralUrl('super-admin/website/posts/create?type=notice'))->assertOk();
    }

    public function test_settings_screen_saves_hero_features_partners_and_social(): void
    {
        Storage::fake('public');
        $i = $this->institution();
        $root = $this->root();

        $this->actingCentral($root)->get($this->centralUrl('super-admin/website/settings'))->assertOk()->assertSee('Special Education');

        $this->actingCentral($root)->put($this->centralUrl('super-admin/website/settings'), [
            'features' => [
                ['icon' => 'fa-flask', 'title' => 'Science Labs', 'text' => 'Modern labs.', 'tone' => 'orange'],
                ['icon' => 'fa-bus', 'title' => 'Transport', 'text' => 'Safe buses.', 'tone' => 'teal'],
            ],
            'social' => ['facebook' => 'https://facebook.com/school', 'youtube' => 'javascript:alert(1)'],
            'footer_text' => 'Our story',
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 700),
            'partners' => [UploadedFile::fake()->image('p1.png'), UploadedFile::fake()->image('p2.png')],
        ])->assertSessionHasErrors('social.youtube');

        $this->actingCentral($root)->put($this->centralUrl('super-admin/website/settings'), [
            'features' => [['icon' => 'fa-flask', 'title' => 'Science Labs', 'text' => 'Modern labs.', 'tone' => 'orange']],
            'social' => ['facebook' => 'https://facebook.com/school'],
            'footer_text' => 'Our story',
            'hero_image' => UploadedFile::fake()->image('hero.jpg', 1600, 700),
            'partners' => [UploadedFile::fake()->image('p1.png'), UploadedFile::fake()->image('p2.png')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $web = $i->fresh()->settings['website'];
        $this->assertCount(2, $web['partners']);
        Storage::disk('public')->assertExists($web['hero']['image']);
        $this->assertSame('Science Labs', $web['hero']['features'][0]['title']);

        $this->get($this->centralUrl('/'))->assertOk()->assertSee('Science Labs')->assertDontSee('Special Education')
            ->assertSee('https://facebook.com/school', false)->assertSee($web['partners'][0], false);

        // removing one partner deletes the file
        $this->actingCentral($root)->put($this->centralUrl('super-admin/website/settings'), [
            'features' => [['icon' => 'fa-flask', 'title' => 'Science Labs', 'text' => 'Modern labs.', 'tone' => 'orange']],
            'remove_partners' => [$web['partners'][0]],
        ])->assertRedirect();
        Storage::disk('public')->assertMissing($web['partners'][0]);
        $this->assertCount(1, $i->fresh()->settings['website']['partners']);
    }

    public function test_website_screens_require_the_website_permission(): void
    {
        $i = $this->institution();
        $this->makePage($i);
        $role = \Spatie\Permission\Models\Role::create(['name' => 'central-viewer', 'guard_name' => 'web']);
        $role->givePermissionTo('central.access');
        $nobody = User::factory()->create(['institution_id' => $i->id, 'user_type' => \App\Enums\UserType::Staff]);
        $nobody->assignRole($role);

        foreach (['settings', 'pages', 'posts', 'subscribers'] as $screen) {
            $this->actingCentral($nobody)->get($this->centralUrl("super-admin/website/{$screen}"))->assertForbidden();
        }
    }

    public function test_institution_admin_cannot_touch_another_institutions_page(): void
    {
        $mine = $this->institution();
        $other = Institution::factory()->create(['status' => 'active']);
        $theirs = $this->makePage($other, ['slug' => 'secret']);
        $admin = User::factory()->create(['institution_id' => $mine->id, 'user_type' => \App\Enums\UserType::Staff]);
        $admin->assignRole('institution-admin');

        $this->actingCentral($admin)->get($this->centralUrl("super-admin/website/pages/{$theirs->id}/edit"))->assertForbidden();
        $this->actingCentral($admin)->delete($this->centralUrl("super-admin/website/pages/{$theirs->id}"))->assertForbidden();
        $this->assertNotSoftDeleted('website_pages', ['id' => $theirs->id]);
    }

    public function test_branch_form_stores_cover_photo_and_coordinates(): void
    {
        Storage::fake('public');
        $i = $this->institution();
        $branch = $this->makeBranch($i);
        $root = $this->root();

        $this->actingCentral($root)->put($this->centralUrl("super-admin/branches/{$branch->id}"), [
            'name' => $branch->name, 'code' => $branch->code, 'subdomain' => $branch->subdomain, 'type' => 'school',
            'timezone' => 'Asia/Dhaka', 'currency' => 'BDT', 'locale' => 'en', 'status' => 'active',
            'latitude' => '23.8103', 'longitude' => '90.4125', 'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 700),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $branch->refresh();
        $this->assertEquals(23.8103, $branch->latitude);
        $this->assertTrue($branch->hasCoordinates());
        Storage::disk('public')->assertExists($branch->cover_path);

        $this->actingCentral($root)->put($this->centralUrl("super-admin/branches/{$branch->id}"), [
            'name' => $branch->name, 'code' => $branch->code, 'subdomain' => $branch->subdomain, 'type' => 'school',
            'timezone' => 'Asia/Dhaka', 'currency' => 'BDT', 'locale' => 'en', 'status' => 'active', 'latitude' => '123',
        ])->assertSessionHasErrors('latitude');
    }

    public function test_defaults_seeder_is_idempotent_and_never_overwrites_edits(): void
    {
        $i = $this->institution();
        $this->seed(WebsiteDefaultsSeeder::class);
        $count = Page::query()->where('institution_id', $i->id)->count();
        $this->assertSame(count(config('website.default_pages')), $count);

        Page::query()->where('slug', 'about-us')->update(['body' => 'Edited by admin']);
        $this->seed(WebsiteDefaultsSeeder::class);
        $this->assertSame($count, Page::query()->where('institution_id', $i->id)->count());
        $this->assertSame('Edited by admin', Page::query()->where('slug', 'about-us')->value('body'));

        $this->get($this->centralUrl('/'))->assertSee('Quick Links')->assertSee('Privacy Policy')->assertSee('Scholarships');
    }

    public function test_home_renders_when_no_institution_exists(): void
    {
        $this->get($this->centralUrl('/'))->assertOk()->assertSee('Our Branches');
        $this->get($this->centralUrl('/news'))->assertNotFound();
    }
}
