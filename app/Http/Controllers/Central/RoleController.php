<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RoleRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->withCount(['permissions', 'users'])->orderByDesc('is_system')->orderBy('label')->get();

        return view('central.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('central.roles.form', ['role' => new Role(['scope' => 'branch', 'portal' => 'admin']), 'matrix' => $this->matrix(), 'granted' => []]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $role = Role::query()->create([
                'name' => $data['name'], 'guard_name' => 'web', 'label' => $data['label'], 'label_bn' => $data['label_bn'] ?? null,
                'scope' => $data['scope'], 'portal' => $data['portal'], 'is_system' => false,
            ]);
            $role->syncPermissions($data['permissions'] ?? []);
            $this->audit->log('role.created', $role, [], ['permissions' => $data['permissions'] ?? []]);
        });

        return redirect()->route('central.roles.index')->with('success', __('Role created.'));
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('central.roles.form', [
            'role' => $role,
            'matrix' => $this->matrix(),
            'granted' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($role, $data) {
            $before = $role->permissions->pluck('name')->sort()->values()->all();

            $role->fill(array_filter([
                'name' => $role->is_system ? null : ($data['name'] ?? null),
                'label' => $data['label'],
                'label_bn' => $data['label_bn'] ?? null,
                'scope' => $role->is_system ? null : ($data['scope'] ?? null),
                'portal' => $role->is_system ? null : ($data['portal'] ?? null),
            ], fn ($v) => $v !== null))->save();

            $role->syncPermissions($data['permissions'] ?? []);
            $after = collect($data['permissions'] ?? [])->sort()->values()->all();
            $this->audit->log('role.permissions-changed', $role, ['permissions' => $before], ['permissions' => $after]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('central.roles.index')->with('success', __('Role updated.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->is_system) {
            return back()->withErrors(['role' => __('System roles cannot be deleted.')]);
        }
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => __('Remove this role from all users before deleting it.')]);
        }

        $this->audit->log('role.deleted', $role, ['name' => $role->name]);
        $role->delete();

        return redirect()->route('central.roles.index')->with('success', __('Role deleted.'));
    }

    /** module => [permission names] */
    protected function matrix(): array
    {
        return Permission::query()->orderBy('module')->orderBy('name')->get(['name', 'module'])
            ->groupBy(fn ($p) => $p->module ?: explode('.', $p->name)[0])
            ->map(fn ($group) => $group->pluck('name')->all())
            ->all();
    }
}
