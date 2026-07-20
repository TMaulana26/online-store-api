<?php

namespace Modules\Acl\Services;

use App\Traits\HandlesIndexQuery;
use Illuminate\Support\Facades\DB;
use Modules\Acl\Models\Role;

class RoleService
{
    use HandlesIndexQuery;

    /**
     * Find a role by its ID.
     */
    public function findById(string $id): Role
    {
        return Role::findOrFail($id);
    }

    /**
     * Display a listing of roles.
     */
    public function index(array $params)
    {
        return $this->handleIndexQuery(
            Role::query(),
            $params,
            ['name', 'guard_name']
        );
    }

    /**
     * Store a newly created role.
     */
    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name']]);

            if (isset($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role->refresh();
        });
    }

    /**
     * Update the specified role.
     */
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            if (isset($data['name'])) {
                $role->name = $data['name'];
                $role->save();
            }

            if (isset($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role->refresh();
        });
    }

    /**
     * Handle bulk operations for roles.
     */
    public function handleBulkOperation(array $ids, string $operation): array
    {
        return DB::transaction(function () use ($ids, $operation) {
            $query = match ($operation) {
                'delete',
                'toggle' => Role::query(),
                'restore',
                'forceDelete' => Role::onlyTrashed(),
                default => throw new \InvalidArgumentException("Invalid operation: {$operation}"),
            };

            $roles = $query->whereIn('id', $ids)->get();
            $foundIds = $roles->pluck('id')->toArray();
            $failedIds = array_values(array_diff($ids, $foundIds));

            if ($roles->isNotEmpty()) {
                match ($operation) {
                    'delete' => Role::whereIn('id', $foundIds)->delete(),
                    'restore' => Role::onlyTrashed()->whereIn('id', $foundIds)->restore(),
                    'forceDelete' => Role::onlyTrashed()->whereIn('id', $foundIds)->forceDelete(),
                    'toggle' => $roles->each(fn ($r) => $r->update(['is_active' => ! $r->is_active])),
                };

                if ($operation !== 'forceDelete') {
                    $roles->each->refresh();
                }
            }

            return [
                'affected' => $roles,
                'failed_ids' => $failedIds,
            ];
        });
    }

    /**
     * Toggle the active status of a role.
     */
    public function toggleStatus(Role $role): Role
    {
        $role->update(['is_active' => ! $role->is_active]);

        return $role;
    }

    /**
     * Restore a soft-deleted role.
     */
    public function restore(string $id): Role
    {
        return DB::transaction(function () use ($id) {
            $role = Role::onlyTrashed()->findOrFail($id);
            $role->restore();

            return $role->refresh();
        });
    }

    /**
     * Force delete a role.
     */
    public function forceDelete(string $id): Role
    {
        return DB::transaction(function () use ($id) {
            $role = Role::onlyTrashed()->findOrFail($id);
            $roleData = clone $role;
            $role->forceDelete();

            return $roleData;
        });
    }

    /**
     * Sync permissions to a role (Replace existing).
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        return DB::transaction(function () use ($role, $permissions) {
            $role->syncPermissions($permissions);

            return $role->refresh();
        });
    }

    /**
     * Give permissions to a role (Additive).
     */
    public function givePermissions(Role $role, array $permissions): Role
    {
        return DB::transaction(function () use ($role, $permissions) {
            $role->givePermissionTo($permissions);

            return $role->refresh();
        });
    }

    /**
     * Revoke permissions from a role.
     */
    public function revokePermissions(Role $role, array $permissions): Role
    {
        return DB::transaction(function () use ($role, $permissions) {
            foreach ($permissions as $permission) {
                $role->revokePermissionTo($permission);
            }

            return $role->refresh();
        });
    }
}
