<?php

namespace Tests\Feature;

use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_domain_serves_public_website(): void
    {
        $this->get($this->centralUrl('/'))->assertOk()->assertSee(config('app.name'));
    }

    public function test_branch_subdomain_resolves_to_its_branch(): void
    {
        $branch = $this->makeBranch(attributes: ['subdomain' => 'branch1', 'name' => 'Dhanmondi Campus']);

        $this->get($this->branchUrl($branch, 'login'))->assertOk()->assertSee('Dhanmondi Campus');
    }

    public function test_ten_branches_each_resolve_to_the_right_branch(): void
    {
        $institution = Institution::factory()->create();
        foreach (range(1, 10) as $i) {
            $this->makeBranch($institution, ['subdomain' => "branch{$i}", 'name' => "Campus Number {$i}"]);
        }

        foreach (range(1, 10) as $i) {
            $this->get("http://branch{$i}.localhost/login")->assertOk()->assertSee("Campus Number {$i}");
        }
    }

    public function test_unknown_subdomain_returns_404(): void
    {
        $this->get('http://nosuchbranch.localhost/login')->assertNotFound();
    }

    public function test_reserved_subdomain_never_maps_to_a_branch(): void
    {
        $this->get('http://api.localhost/login')->assertNotFound();
    }

    public function test_inactive_branch_is_unavailable(): void
    {
        $branch = $this->makeBranch(attributes: ['status' => 'inactive']);

        $this->get($this->branchUrl($branch, 'login'))->assertStatus(503);
    }

    public function test_suspended_institution_blocks_all_its_branches(): void
    {
        $branch = $this->makeBranch(Institution::factory()->suspended()->create());

        $this->get($this->branchUrl($branch, 'login'))->assertForbidden();
    }

    public function test_custom_domain_resolves_to_branch(): void
    {
        $branch = $this->makeBranch(attributes: ['name' => 'Custom Domain Campus']);
        app(\App\Services\Tenancy\BranchService::class)->attachDomain($branch, 'campus.example.edu.bd', true);

        $this->get('http://campus.example.edu.bd/login')->assertOk()->assertSee('Custom Domain Campus');
    }

    public function test_branch_only_routes_are_not_reachable_on_central_domain(): void
    {
        $this->get($this->centralUrl('dashboard'))->assertNotFound();
    }

    public function test_central_admin_routes_are_not_reachable_on_branch_hosts(): void
    {
        $branch = $this->makeBranch();

        $this->get($this->branchUrl($branch, config('tenancy.central_path')))->assertNotFound();
    }

    public function test_new_branch_is_reachable_without_code_changes(): void
    {
        $this->get('http://branch11.localhost/login')->assertNotFound();

        app(\App\Services\Tenancy\BranchService::class)->create(Institution::factory()->create(), [
            'name' => 'Eleventh Campus', 'code' => 'BR11', 'subdomain' => 'branch11', 'type' => 'school',
            'timezone' => 'Asia/Dhaka', 'currency' => 'BDT', 'locale' => 'en', 'status' => 'active',
        ]);

        $this->get('http://branch11.localhost/login')->assertOk()->assertSee('Eleventh Campus');
    }
}
