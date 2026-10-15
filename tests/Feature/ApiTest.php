<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function token(string $host, string $login): string
    {
        return $this->postJson("http://{$host}/api/v1/auth/login", ['login' => $login, 'password' => 'password', 'device_name' => 'phpunit'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.token');
    }

    public function test_login_returns_tenant_bound_token_and_me_works(): void
    {
        $branch = $this->makeBranch(attributes: ['subdomain' => 'branch1']);
        $this->branchUser($branch, 'branch-admin', ['email' => 'api@test.bd']);

        $token = $this->token('branch1.localhost', 'api@test.bd');

        $this->withToken($token)->getJson('http://branch1.localhost/api/v1/auth/me')
            ->assertOk()->assertJsonPath('data.email', 'api@test.bd')->assertJsonPath('data.portal', 'admin');
    }

    public function test_token_from_one_branch_is_rejected_on_another(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution, ['subdomain' => 'branch1']);
        $b = $this->makeBranch($institution, ['subdomain' => 'branch2']);
        $user = $this->branchUser($a, 'branch-admin', ['email' => 'api@test.bd']);
        $user->branches()->attach($b->id); // even with access, the token is bound to branch1

        $token = $this->token('branch1.localhost', 'api@test.bd');

        $this->withToken($token)->getJson('http://branch2.localhost/api/v1/departments')->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_departments_api_is_scoped_and_validated(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution, ['subdomain' => 'branch1']);
        $b = $this->makeBranch($institution, ['subdomain' => 'branch2']);
        $this->inBranch($a, fn () => Department::factory()->count(2)->create());
        $foreign = $this->inBranch($b, fn () => Department::factory()->create());
        $this->branchUser($a, 'branch-admin', ['email' => 'api@test.bd']);
        $token = $this->token('branch1.localhost', 'api@test.bd');

        $this->withToken($token)->getJson('http://branch1.localhost/api/v1/departments')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);

        $this->withToken($token)->getJson("http://branch1.localhost/api/v1/departments/{$foreign->id}")->assertNotFound()
            ->assertJsonPath('success', false);

        $this->withToken($token)->postJson('http://branch1.localhost/api/v1/departments', ['name' => ''])
            ->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors' => ['name', 'code']]);

        $this->withToken($token)->postJson('http://branch1.localhost/api/v1/departments', ['name' => 'Arts', 'code' => 'art', 'branch_id' => $b->id])
            ->assertCreated()->assertJsonPath('data.branch_id', $a->id)->assertJsonPath('data.code', 'ART');
    }

    public function test_api_requires_authentication(): void
    {
        $this->makeBranch(attributes: ['subdomain' => 'branch1']);

        $this->getJson('http://branch1.localhost/api/v1/departments')->assertUnauthorized()->assertJsonPath('success', false);
    }

    public function test_api_permission_is_enforced(): void
    {
        $branch = $this->makeBranch(attributes: ['subdomain' => 'branch1']);
        $this->branchUser($branch, 'librarian', ['email' => 'lib@test.bd']);
        $token = $this->token('branch1.localhost', 'lib@test.bd');

        $this->withToken($token)->getJson('http://branch1.localhost/api/v1/departments')->assertForbidden();
    }

    public function test_branch_list_on_branch_host_only_shows_current_branch(): void
    {
        $institution = Institution::factory()->create();
        $a = $this->makeBranch($institution, ['subdomain' => 'branch1']);
        $this->makeBranch($institution, ['subdomain' => 'branch2']);
        \App\Models\User::factory()->role('institution-admin')->create(['institution_id' => $institution->id, 'email' => 'ia@test.bd']);

        $token = $this->token('branch1.localhost', 'ia@test.bd');

        $this->withToken($token)->getJson('http://branch1.localhost/api/v1/branches')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id);
    }
}
