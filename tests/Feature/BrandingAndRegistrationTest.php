<?php

namespace Tests\Feature;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Institution;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\AuthTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingAndRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function platformSetting(string $key, mixed $value): void
    {
        app(SettingsService::class)->set(AuthTheme::GROUP, $key, $value, 'platform');
    }

    public function test_login_page_uses_new_design_with_footer_and_optional_video(): void
    {
        $this->get($this->centralUrl('login'))->assertOk()
            ->assertSee('au-card', false)->assertSee('Shataj Soft')->assertDontSee('data-yt=', false);

        $this->platformSetting('auth_login_video', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->platformSetting('footer_text', 'Developed By : ABL Engineers');

        $this->get($this->centralUrl('login'))->assertSee('data-yt="dQw4w9WgXcQ"', false)->assertSee('ABL Engineers');

        $this->platformSetting('auth_show_video', false);
        $this->get($this->centralUrl('login'))->assertDontSee('data-yt=', false);
    }

    public function test_youtube_ids_are_parsed_from_common_link_formats(): void
    {
        foreach (['dQw4w9WgXcQ', 'https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5',
                     'https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://youtube.com/shorts/dQw4w9WgXcQ'] as $url) {
            $this->assertSame('dQw4w9WgXcQ', AuthTheme::youtubeId($url), $url);
        }
        $this->assertNull(AuthTheme::youtubeId('https://example.com/video'));
    }

    public function test_registration_is_closed_by_default(): void
    {
        $this->get($this->centralUrl('register'))->assertNotFound();
        $this->post($this->centralUrl('register'), [])->assertNotFound();
        $this->get($this->centralUrl('login'))->assertDontSee(__('Register your institution'));
    }

    public function test_institution_can_register_when_enabled(): void
    {
        $this->platformSetting('allow_registration', true);

        $this->get($this->centralUrl('login'))->assertSee(__('Register your institution'));
        $this->get($this->centralUrl('register'))->assertOk()->assertSee('au-card wide', false);

        $this->post($this->centralUrl('register'), [
            'name' => 'Abdul Karim', 'mobile' => '01711223344', 'email' => 'Karim@Example.com',
            'institution_name' => 'Green Valley School', 'subdomain' => 'green-valley', 'address' => 'Mirpur, Dhaka',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'terms' => 1,
        ])->assertRedirectContains('/super-admin');

        $branch = Branch::query()->where('subdomain', 'green-valley')->firstOrFail();
        $user = User::query()->where('email', 'karim@example.com')->firstOrFail();
        $this->assertSame('Green Valley School', $branch->institution->name);
        $this->assertTrue($user->hasRole('institution-admin'));
        $this->assertSame($branch->institution_id, $user->institution_id);
        $this->assertAuthenticatedAs($user);

        // The new branch portal is live immediately.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('http://green-valley.localhost/login')->assertOk()->assertSee('Green Valley School');
    }

    public function test_registration_validates_mobile_subdomain_and_terms(): void
    {
        $this->platformSetting('allow_registration', true);
        $this->makeBranch(attributes: ['subdomain' => 'taken']);

        $this->post($this->centralUrl('register'), [
            'name' => 'X', 'mobile' => '12345', 'email' => 'x@example.com', 'institution_name' => 'X',
            'subdomain' => 'taken', 'address' => 'Dhaka', 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ])->assertSessionHasErrors(['mobile', 'subdomain', 'terms']);

        $this->assertSame(0, Institution::query()->where('name', 'X')->count());
    }

    public function test_subdomain_availability_check(): void
    {
        $this->platformSetting('allow_registration', true);
        $this->makeBranch(attributes: ['subdomain' => 'taken']);

        $this->getJson($this->centralUrl('register/check-subdomain?subdomain=taken'))->assertJson(['valid' => true, 'available' => false]);
        $this->getJson($this->centralUrl('register/check-subdomain?subdomain=www'))->assertJson(['valid' => false, 'available' => false]);
        $this->getJson($this->centralUrl('register/check-subdomain?subdomain=new-school'))
            ->assertJson(['valid' => true, 'available' => true, 'host' => 'new-school.localhost']);
    }

    public function test_super_admin_saves_platform_branding(): void
    {
        Storage::fake('public');
        $root = User::factory()->platform()->role('super-admin')->create();

        $this->actingCentral($root)->get($this->centralUrl('super-admin/settings'))->assertOk()->assertSee(__('Logo & login / registration page'));

        $this->actingCentral($root)->put($this->centralUrl('super-admin/settings'), [
            'site_name' => 'Shataj Education', 'footer_text' => 'Developed By : Shataj Soft', 'allow_registration' => 1,
            'auth_login_video' => 'https://youtu.be/dQw4w9WgXcQ', 'auth_show_video' => 1, 'auth_dim' => 0.3,
            'auth_login_bg' => UploadedFile::fake()->image('bg.jpg', 1920, 1080),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertTrue(AuthTheme::registrationOpen());
        $this->assertDatabaseHas('system_settings', ['scope_key' => 'platform', 'group' => 'site', 'key' => 'site_name']);
        Storage::disk('public')->assertExists(app(SettingsService::class)->platform('site', 'auth_login_bg'));

        $this->actingCentral($root)->put($this->centralUrl('super-admin/settings'), ['auth_login_video' => 'not a video'])
            ->assertSessionHasErrors('auth_login_video');
    }

    public function test_institution_admin_saves_institution_level_only(): void
    {
        $admin = User::factory()->role('institution-admin')->create();

        $this->actingCentral($admin)->put($this->centralUrl('super-admin/settings'), [
            'footer_text' => 'Developed By : Our IT Team', 'allow_registration' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('system_settings', ['scope_key' => 'i:'.$admin->institution_id, 'key' => 'footer_text']);
        $this->assertDatabaseMissing('system_settings', ['scope_key' => 'platform', 'key' => 'footer_text']);
        $this->assertFalse(AuthTheme::registrationOpen(), 'Only the Super Admin can open registration.');
    }

    public function test_branch_admin_updates_branch_settings_and_login_page(): void
    {
        $branch = $this->makeBranch(attributes: ['subdomain' => 'branch1']);
        $admin = $this->branchUser($branch, 'branch-admin');

        $this->actingOnBranch($admin, $branch)->get($this->branchUrl($branch, 'settings'))->assertOk();
        $this->actingOnBranch($admin, $branch)->put($this->branchUrl($branch, 'settings'), [
            'name_bn' => 'মিরপুর শাখা', 'phone' => '01700000000', 'locale' => 'bn',
            'auth_login_video' => 'dQw4w9WgXcQ', 'auth_show_video' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame('মিরপুর শাখা', $branch->fresh()->name_bn);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($this->branchUrl($branch, 'login'))->assertSee('data-yt="dQw4w9WgXcQ"', false);
    }

    public function test_staff_without_settings_permission_cannot_open_branch_settings(): void
    {
        $branch = $this->makeBranch();
        $librarian = $this->branchUser($branch, 'librarian');

        $this->actingOnBranch($librarian, $branch)->get($this->branchUrl($branch, 'settings'))->assertForbidden();
        $this->actingOnBranch($librarian, $branch)->put($this->branchUrl($branch, 'settings'), ['locale' => 'en'])->assertForbidden();
    }

    public function test_panel_menu_only_lists_what_the_user_may_open(): void
    {
        $branch = $this->makeBranch();
        $setup = $this->academicSetup($branch);
        $accountant = $this->branchUser($branch, 'accountant', ['user_type' => UserType::Staff]);

        $this->actingOnBranch($accountant, $branch)->get($this->branchUrl($branch, 'dashboard'))
            ->assertOk()
            ->assertSee('pm-item', false)
            ->assertSee(__('Student list'))
            ->assertDontSee(route('branch.academics.classes.index', [], false));
    }
}
