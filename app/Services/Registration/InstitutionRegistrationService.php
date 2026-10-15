<?php

namespace App\Services\Registration;

use App\Enums\UserType;
use App\Models\Institution;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\BranchService;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a new institution with its first branch and an Institution Administrator account.
 * The new branch is live immediately at <subdomain>.<base domain>.
 * (Subscription plans / trial limits are attached in Part 7 — SaaS billing.)
 */
class InstitutionRegistrationService
{
    public function __construct(
        protected BranchService $branches,
        protected TenantContext $tenant,
        protected AuditLogger $audit,
    ) {}

    /** @return array{0: Institution, 1: User} */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $institution = Institution::query()->create([
                'name' => $data['institution_name'],
                'slug' => $this->uniqueSlug($data['subdomain']),
                'code' => $this->uniqueCode($data['subdomain']),
                'email' => $data['email'],
                'phone' => $data['mobile'],
                'address' => $data['address'],
                'timezone' => config('app.timezone'),
                'currency' => config('app.currency', 'BDT'),
                'locale' => app()->getLocale(),
                'status' => 'active',
            ]);

            $branch = $this->tenant->runCentral($institution, fn () => $this->branches->create($institution, [
                'name' => $data['institution_name'],
                'code' => 'BR01',
                'subdomain' => $data['subdomain'],
                'type' => 'school_college',
                'email' => $data['email'],
                'phone' => $data['mobile'],
                'address' => $data['address'],
                'timezone' => config('app.timezone'),
                'currency' => config('app.currency', 'BDT'),
                'locale' => app()->getLocale(),
                'status' => 'active',
                'sort_order' => 1,
            ]));

            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['mobile'],
                'user_type' => UserType::Staff,
                'status' => 'active',
                'locale' => app()->getLocale(),
                'password' => $data['password'],
            ]);
            $user->institution_id = $institution->id;
            $user->default_branch_id = $branch->id;
            $user->save();
            $user->assignRole('institution-admin');
            $user->branches()->attach($branch->id, ['is_default' => true]);

            $this->audit->log('institution.registered', $institution, [], [
                'branch' => $branch->subdomain, 'admin' => $user->email,
            ], userId: $user->id, branchId: $branch->id, institutionId: $institution->id);

            app(\App\Services\Saas\SubscriptionService::class)->startDefaultTrial($institution);

            return [$institution, $user];
        });
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'institution';
        $candidate = $slug;
        for ($i = 2; Institution::withTrashed()->where('slug', $candidate)->exists(); $i++) {
            $candidate = $slug.'-'.$i;
        }

        return $candidate;
    }

    protected function uniqueCode(string $base): string
    {
        $code = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $base), 0, 6)) ?: 'INST';
        $candidate = $code;
        for ($i = 2; Institution::withTrashed()->where('code', $candidate)->exists(); $i++) {
            $candidate = $code.$i;
        }

        return $candidate;
    }
}
