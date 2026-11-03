<?php

namespace App\Http\Controllers;

use App\Models\AccessGroup;
use App\Models\Permission;
use App\Models\User;
use App\Services\AccessGrantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccessController extends Controller
{
    public function users(Request $request, AccessGrantScope $scope)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['q'] ?? '');

        return view('access.users', [
            'users' => User::with(['groups.permissions', 'directPermissions'])
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                }))
                ->orderBy('name')->paginate(25)->withQueryString(),
            'search' => $search,
            'groups' => AccessGroup::with('permissions')->orderBy('name')->get()->filter(fn ($group) => $scope->canManageGroup($request->user(), $group)),
            'permissions' => $scope->permissions($request->user()),
            'grantScope' => $scope,
        ]);
    }

    public function groups(Request $request, AccessGrantScope $scope)
    {
        return view('access.groups', [
            'groups' => AccessGroup::with('permissions')->withCount('users')->orderBy('name')->get(),
            'permissions' => $scope->permissions($request->user(), true),
            'grantScope' => $scope,
        ]);
    }

    public function storeUser(Request $request, AccessGrantScope $scope)
    {
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $permissions = $scope->permissions($request->user())->pluck('id')->all();
        $groups = AccessGroup::with('permissions')->get()->filter(fn ($group) => $scope->canManageGroup($request->user(), $group))->pluck('id')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'regex:/^[a-z0-9._-]+$/', 'min:3', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            'sales_location_id' => ['prohibited'],
            'groups' => ['array'], 'groups.*' => ['integer', Rule::in($groups)],
            'permissions' => ['array'], 'permissions.*' => ['integer', Rule::in($permissions)],
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = User::create([
                'name' => $data['name'], 'username' => $data['username'],
                'password' => $data['password'], 'is_active' => $request->boolean('is_active'),
            ]);
            $user->groups()->sync($data['groups'] ?? []);
            $user->directPermissions()->sync($data['permissions'] ?? []);
        });
        return back()->with('success', 'Utilizador criado.');
    }

    public function storeGroup(Request $request, AccessGrantScope $scope)
    {
        $permissions = $scope->permissions($request->user(), true)->pluck('id')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:access_groups,name'],
            'permissions' => ['array'], 'permissions.*' => ['integer', Rule::in($permissions)],
        ]);
        DB::transaction(function () use ($data) {
            $group = AccessGroup::create(['name' => $data['name']]);
            $group->permissions()->sync($data['permissions'] ?? []);
        });
        return back()->with('success', 'Grupo criado.');
    }

    public function editUser(Request $request, User $user, AccessGrantScope $scope)
    {
        $user->load(['groups.permissions', 'directPermissions']);
        abort_unless($scope->canManageUser($request->user(), $user), 403);

        return view('access.edit-user', [
            'user' => $user,
            'groups' => AccessGroup::with('permissions')->orderBy('name')->get()->filter(fn ($group) => $scope->canManageGroup($request->user(), $group)),
            'permissions' => $scope->permissions($request->user()),
        ]);
    }

    public function updateUser(Request $request, User $user, AccessGrantScope $scope)
    {
        $user->load(['groups.permissions', 'directPermissions']);
        abort_unless($scope->canManageUser($request->user(), $user), 403);
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $permissions = $scope->permissions($request->user())->pluck('id')->all();
        $groups = AccessGroup::with('permissions')->get()->filter(fn ($group) => $scope->canManageGroup($request->user(), $group))->pluck('id')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'regex:/^[a-z0-9._-]+$/', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:10', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            'sales_location_id' => ['prohibited'],
            'groups' => ['array'], 'groups.*' => ['integer', Rule::in($groups)],
            'permissions' => ['array'], 'permissions.*' => ['integer', Rule::in($permissions)],
        ]);

        DB::transaction(function () use ($request, $data, $user) {
            $attributes = ['name' => $data['name'], 'username' => $data['username']];
            if (! empty($data['password'])) $attributes['password'] = $data['password'];
            if (! $user->is_super_admin) $attributes['is_active'] = $request->boolean('is_active');
            $user->update($attributes);
            if (! $user->is_super_admin) {
                $user->groups()->sync($data['groups'] ?? []);
                $user->directPermissions()->sync($data['permissions'] ?? []);
            }
        });

        return redirect()->route('access.users')->with('success', 'Utilizador atualizado.');
    }

    public function editGroup(Request $request, AccessGroup $group, AccessGrantScope $scope)
    {
        $group->load('permissions');
        abort_unless($scope->canManageGroup($request->user(), $group), 403);
        return view('access.edit-group', [
            'group' => $group,
            'permissions' => $scope->permissions($request->user(), true),
        ]);
    }

    public function updateGroup(Request $request, AccessGroup $group, AccessGrantScope $scope)
    {
        $group->load('permissions');
        abort_unless($scope->canManageGroup($request->user(), $group), 403);
        $permissions = $scope->permissions($request->user(), true)->pluck('id')->all();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('access_groups', 'name')->ignore($group->id)],
            'permissions' => ['array'], 'permissions.*' => ['integer', Rule::in($permissions)],
        ]);

        DB::transaction(function () use ($data, $group) {
            $group->update(['name' => $data['name']]);
            $group->permissions()->sync($data['permissions'] ?? []);
        });

        return redirect()->route('access.groups')->with('success', 'Grupo atualizado.');
    }
}
