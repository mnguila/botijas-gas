<?php

namespace App\Services;

use App\Models\AccessGroup;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

class AccessGrantScope
{
    private array $moduleCache = [];
    private array $permissionCache = [];

    public function modules(User $actor): Collection
    {
        return $this->moduleCache[$actor->id] ??= collect(config('access.modules'))
            ->filter(fn (array $module) => $actor->is_super_admin || $actor->hasPermission($module['grant']));
    }

    public function permissions(User $actor, bool $forGroup = false): Collection
    {
        $cacheKey = $actor->id.':'.(int) $forGroup;
        if (isset($this->permissionCache[$cacheKey])) return $this->permissionCache[$cacheKey];

        $keys = $this->modules($actor)->flatMap(fn (array $module) => $module['permissions']);
        if ($actor->is_super_admin) {
            $keys = $keys->concat(collect(config('access.modules'))->pluck('grant'));
        }
        if ($forGroup) {
            $keys = $keys->reject(fn (string $key) => $key === 'sales.profit.view');
        }

        return $this->permissionCache[$cacheKey] = Permission::whereIn('key', $keys->all())->orderBy('id')->get();
    }

    public function canManageGroup(User $actor, AccessGroup $group): bool
    {
        if ($actor->is_super_admin) return true;

        $allowed = $this->permissions($actor, true)->pluck('id');
        return $group->permissions->every(fn (Permission $permission) => $allowed->contains($permission->id));
    }

    public function canManageUser(User $actor, User $target): bool
    {
        if ($target->is_super_admin) return $actor->id === $target->id;
        if ($actor->is_super_admin) return true;

        $allowed = $this->permissions($actor)->pluck('id');
        if (! $target->directPermissions->every(fn (Permission $permission) => $allowed->contains($permission->id))) return false;

        return $target->groups->every(fn (AccessGroup $group) => $this->canManageGroup($actor, $group));
    }
}
