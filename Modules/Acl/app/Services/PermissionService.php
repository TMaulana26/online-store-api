<?php

namespace Modules\Acl\Services;

use App\Traits\HandlesIndexQuery;
use Illuminate\Support\Facades\DB;
use Modules\Acl\Models\Permission;

class PermissionService
{
    use HandlesIndexQuery;

    /**
     * Find a permission by its ID.
     */
    public function findById(string $id): Permission
    {
        return Permission::findOrFail($id);
    }

    /**
     * Display a listing of permissions.
     */
    public function index(array $params)
    {
        return $this->handleIndexQuery(
            Permission::query(),
            $params,
            ['name', 'guard_name']
        );
    }

    /**
     * Store a newly created permission.
     */
    public function store(array $data): Permission
    {
        return Permission::create($data);
    }

    /**
     * Update the specified permission.
     */
    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);

        return $permission->refresh();
    }

    /**
     * Handle bulk operations for permissions.
     */
    public function handleBulkOperation(array $ids, string $operation): array
    {
        return DB::transaction(function () use ($ids, $operation) {
            $query = match ($operation) {
                'delete',
                'toggle' => Permission::query(),
                'restore',
                'forceDelete' => Permission::onlyTrashed(),
                default => throw new \InvalidArgumentException("Invalid operation: {$operation}"),
            };

            $permissions = $query->whereIn('id', $ids)->get();
            $foundIds = $permissions->pluck('id')->toArray();
            $failedIds = array_values(array_diff($ids, $foundIds));

            if ($permissions->isNotEmpty()) {
                match ($operation) {
                    'delete' => Permission::whereIn('id', $foundIds)->delete(),
                    'restore' => Permission::onlyTrashed()->whereIn('id', $foundIds)->restore(),
                    'forceDelete' => Permission::onlyTrashed()->whereIn('id', $foundIds)->forceDelete(),
                    'toggle' => $permissions->each(fn ($p) => $p->update(['is_active' => ! $p->is_active])),
                };

                if ($operation !== 'forceDelete') {
                    $permissions->each->refresh();
                }
            }

            return [
                'affected' => $permissions,
                'failed_ids' => $failedIds,
            ];
        });
    }

    /**
     * Toggle the active status of a permission.
     */
    public function toggleStatus(Permission $permission): Permission
    {
        $permission->update(['is_active' => ! $permission->is_active]);

        return $permission;
    }

    /**
     * Restore a soft-deleted permission.
     */
    public function restore(string $id): Permission
    {
        return DB::transaction(function () use ($id) {
            $permission = Permission::onlyTrashed()->findOrFail($id);
            $permission->restore();

            return $permission->refresh();
        });
    }

    /**
     * Force delete a permission.
     */
    public function forceDelete(string $id): Permission
    {
        return DB::transaction(function () use ($id) {
            $permission = Permission::onlyTrashed()->findOrFail($id);
            $permissionData = clone $permission;
            $permission->forceDelete();

            return $permissionData;
        });
    }

    /**
     * Sync roles for a permission (Replace existing roles).
     */
    public function syncRoles(Permission $permission, array $roles): Permission
    {
        return DB::transaction(function () use ($permission, $roles) {
            $permission->syncRoles($roles);

            return $permission->refresh();
        });
    }

    /**
     * Assign roles to a permission (Additive).
     */
    public function assignRoles(Permission $permission, array $roles): Permission
    {
        return DB::transaction(function () use ($permission, $roles) {
            $permission->assignRole($roles);

            return $permission->refresh();
        });
    }

    /**
     * Remove roles from a permission.
     */
    public function removeRoles(Permission $permission, array $roles): Permission
    {
        return DB::transaction(function () use ($permission, $roles) {
            foreach ($roles as $role) {
                $permission->removeRole($role);
            }

            return $permission->refresh();
        });
    }
}
