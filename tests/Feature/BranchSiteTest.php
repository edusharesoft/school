<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\HR\Employee;
use App\Models\Institution;
use App\Models\User;
use App\Models\Website\Page;
use App\Models\Website\Post;
use App\Models\Website\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The public website of a branch (its own address) and the screens the branch administrator edits it with. */
class BranchSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function world(): array
    {
        $institution = Institution::factory()->create(['status' => 'active']);
        $branch = $this->makeBranch($institution, ['name' => 'Dhaka Campus', 'phone' => '+8801711111111', 'address' => 'Tejgaon, Dhaka', 'latitude' => 23.76, 'longitude' => 90.39]);
        $other = $this->makeBranch($institution, ['name' => 'Chattogram Campus', 'latitude' => 22.35, 'longitude' => 91.78]);
        $foreign = $this->makeBranch(Institution::factory()->create(['status' => 'active']), ['name' => 'Foreign Campus']);
        $admin = $this->branchUser($branch, 'branch-admin');

        return compact('institution', 'branch', 'other', 'foreign', 'admin');
    }

    protected function makePost(Branch $branch, array $attrs = []): Post
    {
        $p = new Post($attrs + ['type' => 'news', 'slug' => 'p-'.uniqid(), 'title' => 'A story', 'status' => 'published', 'published_at' => now()->subHour()]);
        $p->institution_id = $branch->institution_id;
        $p->branch_id = $branch->id;
        $p->save();

        return $p;
    }

    protected function img(string $name = 'x.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 400);
    }

    public function test_the_branch_address_shows_the_branch_website_not_the_login_page(): void
    {
        $w = $this->world();

        $this->get($this->branchUrl($w['branch'], '/'))->assertOk()
            ->assertSee('Dhaka Campus')->assertSee('+8801711111111')->assertSee('Our Branches')->assertSee('Special Education')
            ->assertSee('Chattogram Campus')->assertDontSee('Foreign Campus')
            ->assertSee(route('login'), false);
        // Blocks without content stay hidden.
        $this->get($this->branchUrl($w['branch'], '/'))->assertDontSee('Our Team')->assertDontSee('Our Teacher')->assertDontSee('Discover our School')->assertDontSee('Latest News');
    }

    public function test_a_signed_in_user_still_sees_the_website_with_a_dashboard_link(): void
    {
        $w = $this->world();

        $this->actingOnBranch($w['admin'], $w['branch'])->get($this->branchUrl($w['branch'], '/'))->assertOk()->assertSee('Dashboard');
    }

    public function test_administrator_builds_the_whole_home_page_and_the_public_sees_it(): void
    {
        Storage::fake('public');
        $w = $this->world();
        $b = $w['branch'];

        $this->actingOnBranch($w['admin'], $b)->put($this->branchUrl($b, 'website/settings'), [
            'hero_image' => $this->img('hero.jpg'),
            'features' => [['icon' => 'fa-flask', 'title' => 'Science Labs', 'text' => 'Modern labs', 'tone' => 'orange']],
            'about' => ['title' => 'Know our campus', 'subtitle' => 'Learning for life', 'text' => "Founded long ago.\nStill growing.", 'years' => 15, 'years_label' => 'Years of teaching',
                'points' => ['Disciplined', 'Caring', ''], 'button_text' => 'More about us', 'button_url' => '/page/about-us'],
            'about_image_main' => $this->img('a.jpg'), 'about_image_second' => $this->img('b.jpg'),
            'messages' => [['name' => 'Group Captain Rahman', 'designation' => 'Chairman', 'text' => 'Dear parents, welcome.'], ['name' => 'Wing Commander Karim', 'designation' => 'Principal']],
            'message_photos' => [0 => $this->img('m1.jpg'), 1 => $this->img('m2.jpg')],
            'team' => [['name' => 'Santanu Chowdhury', 'designation' => 'Principal', 'note' => 'GUP, psc'], ['name' => '', 'designation' => 'Empty slot is dropped']],
            'team_photos' => [0 => $this->img('t1.jpg')],
            'teachers' => [['name' => 'Md. Sajjad Hossain', 'designation' => 'Vice-Principal (College)']],
            'social' => ['facebook' => 'https://facebook.com/dhaka'],
            'footer_text' => 'Dhaka campus footer text.',
        ])->assertRedirect(route('branch.website.settings'))->assertSessionHasNoErrors();

        $saved = $b->fresh()->settings['website'];
        $this->assertCount(1, $saved['hero']['features']);
        $this->assertSame(['Disciplined', 'Caring'], $saved['about']['points']);
        $this->assertCount(1, $saved['team']['items'], 'a slot without a name is dropped');
        foreach ([$saved['hero']['image'], $saved['about']['image_main'], $saved['messages']['items'][0]['photo'], $saved['team']['items'][0]['photo']] as $path) {
            $this->assertStringStartsWith('website/branch-'.$b->id.'/', $path);
            Storage::disk('public')->assertExists($path);
        }

        $this->get($this->branchUrl($b, '/'))->assertOk()
            ->assertSee('Science Labs')->assertSee('Know our campus')->assertSee('Learning for life')->assertSee('Still growing.')
            ->assertSee('Years of teaching')->assertSee('15+')->assertSee('Disciplined')->assertSee('More about us')
            ->assertSee('Group Captain Rahman')->assertSee('Chairman')->assertSee('Our Team')->assertSee('Santanu Chowdhury')->assertSee('GUP, psc')
            ->assertSee('Our Teacher')->assertSee('Md. Sajjad Hossain')->assertSee('Vice-Principal (College)')
            ->assertSee('https://facebook.com/dhaka', false)->assertSee('Dhaka campus footer text.')
            ->assertDontSee('Empty slot is dropped');

        // The message page shows the full text; a message without text still has a (short) page.
        $this->get($this->branchUrl($b, 'message/1'))->assertOk()->assertSee('Dear parents, welcome.')->assertSee('Chairman');
        $this->get($this->branchUrl($b, 'message/2'))->assertOk()->assertSee('Wing Commander Karim');
        $this->get($this->branchUrl($b, 'message/3'))->assertNotFound();
    }

    public function test_saving_again_keeps_photos_drops_removed_rows_and_ignores_forged_paths(): void
    {
        Storage::fake('public');
        $w = $this->world();
        $b = $w['branch'];
        $page = $this->actingOnBranch($w['admin'], $b);

        $page->put($this->branchUrl($b, 'website/settings'), [
            'team' => [['name' => 'Keeper', 'designation' => 'Principal'], ['name' => 'Goner', 'designation' => 'Adjutant']],
            'team_photos' => [0 => $this->img('k.jpg'), 1 => $this->img('g.jpg')],
        ])->assertSessionHasNoErrors();
        [$keep, $gone] = array_column($b->fresh()->settings['website']['team']['items'], 'photo');

        // Keep row 0 (photo path sent back), drop row 1, and try to smuggle in a foreign path.
        $page->put($this->branchUrl($b, 'website/settings'), [
            'team' => [['name' => 'Keeper', 'designation' => 'Principal', 'photo' => $keep], ['name' => 'Sneaky', 'photo' => 'website/branch-999/people/other.jpg']],
        ])->assertSessionHasNoErrors();

        $items = $b->fresh()->settings['website']['team']['items'];
        $this->assertSame($keep, $items[0]['photo']);
        $this->assertNull($items[1]['photo'], 'a path that is not in the saved list is ignored');
        Storage::disk('public')->assertExists($keep);
        Storage::disk('public')->assertMissing($gone);
    }

    public function test_choosing_an_employee_copies_their_photo_for_the_public_site(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $w = $this->world();
        $b = $w['branch'];
        Storage::disk('local')->put('private/emp.jpg', 'jpegbytes');
        $employee = $this->inBranch($b, function () {
            $e = new Employee(['name' => 'Humyra Karim', 'employee_type' => 'teacher', 'employment_type' => 'permanent', 'gender' => 'female', 'phone' => '01711000000', 'joining_date' => '2020-01-01']);
            $e->employee_code = 'E-1';
            $e->photo_path = 'private/emp.jpg';
            $e->save();

            return $e;
        });

        $this->actingOnBranch($w['admin'], $b)->put($this->branchUrl($b, 'website/settings'), [
            'teachers' => [['employee_id' => $employee->id, 'name' => 'Humyra Karim', 'designation' => 'Vice-Principal (SV)']],
        ])->assertSessionHasNoErrors();

        $photo = $b->fresh()->settings['website']['teachers']['items'][0]['photo'];
        $this->assertStringStartsWith('website/branch-'.$b->id.'/people/', $photo);
        $this->assertSame('jpegbytes', Storage::disk('public')->get($photo));
        $this->assertNotNull(Employee::withoutGlobalScopes()->find($employee->id)->photo_path, 'the private original stays private');

        // An employee of another branch cannot be chosen.
        $foreignEmp = $this->inBranch($w['other'], function () {
            $e = new Employee(['name' => 'Elsewhere', 'employee_type' => 'teacher', 'employment_type' => 'permanent', 'gender' => 'male', 'phone' => '01711000001', 'joining_date' => '2020-01-01']);
            $e->employee_code = 'E-2';
            $e->save();

            return $e;
        });
        $this->actingOnBranch($w['admin'], $b)->put($this->branchUrl($b, 'website/settings'), [
            'teachers' => [['employee_id' => $foreignEmp->id, 'name' => 'Elsewhere']],
        ])->assertSessionHasErrors('teachers.0.employee_id');
    }

    public function test_settings_reject_bad_input(): void
    {
        $w = $this->world();
        $b = $w['branch'];

        $this->actingOnBranch($w['admin'], $b)->put($this->branchUrl($b, 'website/settings'), [
            'about' => ['button_url' => 'javascript:alert(1)'],
            'social' => ['facebook' => 'not a url'],
            'hero_image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            'team' => array_fill(0, 7, ['name' => 'Too many']),
        ])->assertSessionHasErrors(['about.button_url', 'social.facebook', 'hero_image', 'team']);
    }

    public function test_only_staff_with_website_permission_edit_the_site(): void
    {
        $w = $this->world();
        $b = $w['branch'];
        $teacher = $this->branchUser($b, 'teacher');
        $principal = $this->branchUser($b, 'principal');

        $this->actingOnBranch($teacher, $b)->get($this->branchUrl($b, 'website/settings'))->assertForbidden();
        $this->actingOnBranch($teacher, $b)->put($this->branchUrl($b, 'website/settings'), [])->assertForbidden();
        $this->actingOnBranch($teacher, $b)->get($this->branchUrl($b, 'website/posts'))->assertForbidden();
        $this->actingOnBranch($principal, $b)->get($this->branchUrl($b, 'website/settings'))->assertOk();
        $this->actingOnBranch($principal, $b)->get($this->branchUrl($b, 'website/posts/create'))->assertForbidden(); // principal may edit settings, not publish posts
        $this->actingOnBranch($w['admin'], $b)->get($this->branchUrl($b, 'website/settings'))->assertOk()->assertSee('Discover our School');
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->get($this->branchUrl($b, 'website/settings'))->assertRedirect(route('login'));
    }

    public function test_branch_news_and_notices_belong_to_the_branch_site_only(): void
    {
        $w = $this->world();
        $b = $w['branch'];
        $this->makePost($b, ['title' => 'Dhaka sports day']);
        $this->makePost($b, ['title' => 'Dhaka holiday notice', 'type' => 'notice']);
        $this->makePost($b, ['title' => 'Dhaka draft', 'status' => 'draft']);
        $this->makePost($w['other'], ['title' => 'Chattogram only story']);
        $central = new Post(['type' => 'news', 'slug' => 'central-story', 'title' => 'Institution wide story', 'status' => 'published', 'published_at' => now()->subHour()]);
        $central->institution_id = $b->institution_id;
        $central->save();

        $home = $this->get($this->branchUrl($b, '/'))->assertOk()->assertSee('Latest News')->assertSee('Dhaka sports day')
            ->assertDontSee('Chattogram only story')->assertDontSee('Institution wide story')->assertDontSee('Dhaka draft')->assertDontSee('Dhaka holiday notice');
        $this->assertStringContainsString('/news/', $home->getContent());

        $this->get($this->branchUrl($b, 'news'))->assertOk()->assertSee('Dhaka sports day')->assertDontSee('Chattogram only story');
        $this->get($this->branchUrl($b, 'public-notices'))->assertOk()->assertSee('Dhaka holiday notice')->assertDontSee('Dhaka sports day');
        $slug = Post::query()->where('title', 'Dhaka sports day')->value('slug');
        $this->get($this->branchUrl($b, 'news/'.$slug))->assertOk()->assertSee('Dhaka sports day');
        $this->get($this->branchUrl($b, 'news/central-story'))->assertNotFound();
        $this->get($this->branchUrl($w['other'], 'news/'.$slug))->assertNotFound();

        // …and the institution site never shows branch posts.
        $this->get($this->centralUrl('/'))->assertSee('Institution wide story')->assertDontSee('Dhaka sports day');
        $this->get($this->centralUrl('news/'.$slug))->assertNotFound();
    }

    public function test_administrator_manages_branch_news_and_cannot_touch_other_sites(): void
    {
        $w = $this->world();
        $b = $w['branch'];
        $as = fn () => $this->actingOnBranch($w['admin'], $b);

        $as()->post($this->branchUrl($b, 'website/posts'), ['type' => 'news', 'title' => 'Annual function', 'status' => 'published', 'published_at' => now()->subMinute()->format('Y-m-d\TH:i')])
            ->assertRedirect(route('branch.website.posts.index'))->assertSessionHasNoErrors();
        $post = Post::query()->where('title', 'Annual function')->firstOrFail();
        $this->assertSame($b->id, $post->branch_id);
        $this->assertSame($b->institution_id, $post->institution_id);

        $as()->get($this->branchUrl($b, 'website/posts'))->assertOk()->assertSee('Annual function');
        $as()->put($this->branchUrl($b, 'website/posts/'.$post->id), ['title' => 'Annual function 2026', 'status' => 'published'])->assertSessionHasNoErrors();
        $this->assertSame('Annual function 2026', $post->fresh()->title);

        // A post of another branch or of the institution site is invisible to this administrator.
        $theirs = $this->makePost($w['other'], ['title' => 'Theirs']);
        $central = new Post(['type' => 'news', 'slug' => 'c1', 'title' => 'Central', 'status' => 'published']);
        $central->institution_id = $b->institution_id;
        $central->save();
        $as()->get($this->branchUrl($b, 'website/posts'))->assertDontSee('Theirs')->assertDontSee('Central');
        foreach ([$theirs, $central] as $foreign) {
            $as()->get($this->branchUrl($b, 'website/posts/'.$foreign->id.'/edit'))->assertNotFound();
            $as()->put($this->branchUrl($b, 'website/posts/'.$foreign->id), ['title' => 'Hacked', 'status' => 'published'])->assertNotFound();
            $as()->delete($this->branchUrl($b, 'website/posts/'.$foreign->id))->assertNotFound();
        }
        $this->assertSame('Theirs', $theirs->fresh()->title);

        $as()->delete($this->branchUrl($b, 'website/posts/'.$post->id))->assertRedirect();
        $this->assertNull(Post::query()->find($post->id));
    }

    public function test_pages_menu_default_pages_and_the_institution_site_stay_separate(): void
    {
        $w = $this->world();
        $b = $w['branch'];
        $central = new Page(['slug' => 'about-us', 'title' => 'Institution About', 'placement' => 'menu', 'status' => 'published', 'body' => 'central']);
        $central->institution_id = $b->institution_id;
        $central->save();

        $this->actingOnBranch($w['admin'], $b)->post($this->branchUrl($b, 'website/pages/defaults'))->assertRedirect(route('branch.website.pages.index'));
        $count = Page::query()->where('branch_id', $b->id)->count();
        $this->assertSame(count(config('website.default_pages')), $count);
        // The institution already uses the slug "about-us"; the branch page gets its own.
        $about = Page::query()->where('branch_id', $b->id)->where('title', 'About Us')->firstOrFail();
        $this->assertSame('about-us-'.$b->id, $about->slug);
        // Idempotent.
        $this->actingOnBranch($w['admin'], $b)->post($this->branchUrl($b, 'website/pages/defaults'))->assertRedirect();
        $this->assertSame($count, Page::query()->where('branch_id', $b->id)->count());

        $about->update(['body' => 'Welcome to the Dhaka campus.']);
        $this->get($this->branchUrl($b, '/'))->assertOk()->assertSee('About Us')->assertSee('Notices')->assertSee('Contact')->assertSee('Privacy Policy')
            ->assertSee(route('site.page', $about->slug), false)->assertDontSee('Institution About');
        $this->get($this->branchUrl($b, 'page/'.$about->slug))->assertOk()->assertSee('Welcome to the Dhaka campus.');
        $this->get($this->branchUrl($b, 'page/about-us'))->assertNotFound(); // institution page, not the branch's
        $this->get($this->branchUrl($w['other'], 'page/'.$about->slug))->assertNotFound();
        $this->get($this->centralUrl('page/'.$about->slug))->assertNotFound();
        $this->get($this->centralUrl('/'))->assertSee('Institution About')->assertDontSee('Welcome to the Dhaka campus.');

        // Draft pages are not public.
        $about->update(['status' => 'draft']);
        $this->get($this->branchUrl($b, 'page/'.$about->slug))->assertNotFound();
        // Another branch's admin cannot edit it.
        $otherAdmin = $this->branchUser($w['other'], 'branch-admin');
        $this->actingOnBranch($otherAdmin, $w['other'])->get($this->branchUrl($w['other'], 'website/pages/'.$about->id.'/edit'))->assertNotFound();
    }

    public function test_branch_newsletter_signup_is_validated_and_stored_for_the_institution(): void
    {
        $w = $this->world();
        $b = $w['branch'];

        $this->post($this->branchUrl($b, 'newsletter'), ['email' => 'Parent@Example.com'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(1, Subscriber::query()->where('institution_id', $b->institution_id)->where('email', 'parent@example.com')->count());
        $this->post($this->branchUrl($b, 'newsletter'), ['email' => 'parent@example.com'])->assertRedirect();
        $this->assertSame(1, Subscriber::query()->count(), 'no duplicate');
        $this->post($this->branchUrl($b, 'newsletter'), ['email' => 'nope'])->assertSessionHasErrors('email');
        $this->post($this->branchUrl($b, 'newsletter'), ['email' => 'bot@example.com', 'website' => 'http://spam'])->assertRedirect();
        $this->assertSame(1, Subscriber::query()->count(), 'honeypot');
    }

    public function test_content_of_one_branch_never_appears_on_another_branch_site(): void
    {
        Storage::fake('public');
        $w = $this->world();
        $this->actingOnBranch($w['admin'], $w['branch'])->put($this->branchUrl($w['branch'], 'website/settings'), [
            'about' => ['text' => 'Dhaka secret text'], 'team' => [['name' => 'Dhaka Principal']], 'footer_text' => 'Dhaka footer',
        ])->assertSessionHasNoErrors();

        $this->get($this->branchUrl($w['branch'], '/'))->assertSee('Dhaka secret text')->assertSee('Dhaka Principal');
        $this->get($this->branchUrl($w['other'], '/'))->assertOk()->assertDontSee('Dhaka secret text')->assertDontSee('Dhaka Principal')->assertDontSee('Dhaka footer');
    }

    public function test_the_branch_without_its_own_hero_and_footer_uses_the_institution_ones(): void
    {
        $w = $this->world();
        $inst = $w['institution'];
        $inst->forceFill(['settings' => ['website' => ['hero' => ['image' => 'https://img.example.com/hero.jpg'], 'footer' => ['text' => 'Institution footer words'], 'social' => ['facebook' => 'https://facebook.com/inst']]]])->save();

        $this->get($this->branchUrl($w['branch'], '/'))->assertOk()->assertSee('https://img.example.com/hero.jpg', false)->assertSee('Institution footer words')->assertSee('https://facebook.com/inst', false);
    }

    public function test_inactive_or_unknown_branch_addresses_do_not_serve_a_site(): void
    {
        $w = $this->world();
        $w['other']->forceFill(['status' => 'inactive'])->save();

        $this->get($this->branchUrl($w['other'], '/'))->assertStatus(503);
        $this->get('http://nobody.'.config('tenancy.base_domain').'/')->assertNotFound();
    }
}
