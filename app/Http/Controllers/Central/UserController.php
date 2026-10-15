<?php

namespace App\Http\Controllers\Central;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UserRequest;
use App\Models\Branch;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Central user management: accounts, roles and branch access for the institution.
 * (Branch-level staff/student/parent records with profiles arrive in Part 3 and link to these accounts.)
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $actor = $request->user();

        $branches = Branch::query()->visibleTo($actor)->ordered()->pluck('name', 'id');

        $users = User::query()
            ->with(['roles:id,name,label', 'permissions:id,name', 'branches:id,name'])
            ->when(! $actor->isPlatformOwner(), fn (Builder $q) => $q->where('institution_id', $actor->institution_id))
            ->search($request->string('q')->toString())
            ->when($request->string('type')->toString(), fn ($q, $t) => $q->where('user_type', $t))
            ->when($request->string('role')->toString(), fn ($q, $r) => $q->whereHas('roles', fn ($rq) => $rq->where('name', $r)))
            ->when($request->integer('branch_id'), function ($q, $id) use ($branches) {
                abort_unless($branches->has($id), 404);
                $q->whereHas('branches', fn ($b) => $b->where('branches.id', $id));
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('central.users.index', [
            'users' => $users,
            'branches' => $branches,
            'roles' => Role::query()->orderBy('label')->pluck('label', 'name'),
            'types' => UserType::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        return view('central.users.form', $this->formData($request, new User(['status' => 'active', 'user_type' => 'staff'])));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($request, $data) {
            $user = new User(Arr::only($data, ['name', 'name_bn', 'email', 'username', 'phone', 'user_type', 'status', 'locale', 'password']));
            $user->institution_id = $request->targetInstitutionId();
            $user->default_branch_id = $data['default_branch_id'] ?? ($data['branches'][0] ?? null);
            $user->save();

            $user->syncRoles($data['roles']);
            $this->syncBranches($user, $data['branches'] ?? [], $request->user());

            return $user;
        });

        return redirect()->route('central.users.index')->with('success', __('User :name created.', ['name' => $user->name]));
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('central.users.form', $this->formData($request, $user->load('roles', 'branches')));
    }

    /** Lost phone: an administrator who may edit this user switches their two-factor authentication off. */
    public function resetTwoFactor(Request $request, User $user, \App\Services\Audit\AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $user);
        abort_if($user->is($request->user()), 403, __('Use the Account security page for your own account.'));
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        $audit->log('auth.2fa.reset_by_admin', $user, userId: $request->user()->id);

        return back()->with('success', __('Two-factor authentication was reset for :name.', ['name' => $user->name]));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $user, $data) {
            $attributes = Arr::only($data, ['name', 'name_bn', 'email', 'username', 'phone', 'user_type', 'status', 'locale']);
            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
                $user->password_changed_at = now();
            }
            $user->fill($attributes);
            $user->default_branch_id = $data['default_branch_id'] ?? ($data['branches'][0] ?? null);
            $user->save();

            $before = $user->roles->pluck('name')->sort()->values()->all();
            $user->syncRoles($data['roles']);
            $after = collect($data['roles'])->sort()->values()->all();
            if ($before !== $after) {
                app(\App\Services\Audit\AuditLogger::class)->log('roles.changed', $user, ['roles' => $before], ['roles' => $after]);
            }

            $this->syncBranches($user, $data['branches'] ?? [], $request->user());
        });

        return redirect()->route('central.users.index')->with('success', __('User updated.'));
    }

    protected function syncBranches(User $user, array $branchIds, User $actor): void
    {
        $default = (int) $user->default_branch_id;
        $sync = [];
        foreach (array_unique(array_map('intval', $branchIds)) as $id) {
            $sync[$id] = ['is_default' => $id === $default, 'granted_by' => $actor->id];
        }
        $user->branches()->sync($sync);
    }

    protected function formData(Request $request, User $user): array
    {
        $actor = $request->user();

        return [
            'user' => $user,
            'branches' => Branch::query()->visibleTo($actor)->ordered()->get(['id', 'name', 'institution_id']),
            'roles' => Role::query()
                ->when(! $actor->isSuperAdmin(), fn ($q) => $q->whereNotIn('name', config('school.protected_roles')))
                ->orderBy('scope')->orderBy('label')->get(),
            'types' => Arr::except(UserType::options(), ['platform']),
            'institutions' => $actor->isPlatformOwner() ? Institution::query()->orderBy('name')->pluck('name', 'id') : collect(),
        ];
    }
}
