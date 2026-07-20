<?php

namespace Modules\Acl\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\HandlesBulkAndSoftDeletes;
use Illuminate\Http\JsonResponse;
use Modules\Acl\Http\Requests\Role\AssignPermissionRequest;
use Modules\Acl\Http\Requests\Role\IndexRoleRequest;
use Modules\Acl\Http\Requests\Role\StoreRoleRequest;
use Modules\Acl\Http\Requests\Role\UpdateRoleRequest;
use Modules\Acl\Models\Role;
use Modules\Acl\Services\RoleService;
use Modules\Acl\Transformers\RoleResource;

class RoleController extends Controller
{
    use HandlesBulkAndSoftDeletes;

    public function __construct(
        protected RoleService $roleService
    ) {}

    protected function getService()
    {
        return $this->roleService;
    }

    protected function getResourceClass(): string
    {
        return RoleResource::class;
    }

    protected function getModelName(): string
    {
        return 'role';
    }

    protected function getEagerLoadRelations(): array
    {
        return ['permissions'];
    }

    /**
     * Display a listing of roles.
     */
    public function index(IndexRoleRequest $request): JsonResponse
    {
        $roles = $this->roleService->index($request->validated());

        if ($request->input('with_permissions', false)) {
            $roles->load('permissions');
        }

        if ($request->input('with_users', false)) {
            $roles->load('users');
        }

        return $this->paginatedResponse(
            RoleResource::collection($roles),
            'Roles retrieved successfully.'
        );
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->store($request->validated());

        return $this->resourceResponse(
            new RoleResource($role->load('permissions')),
            'Role created successfully.',
            201
        );
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        return $this->resourceResponse(
            new RoleResource($role->load('permissions', 'users')),
            'Role details retrieved successfully.'
        );
    }

    /**
     * Update the specified role.
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->update($role, $request->validated());

        return $this->resourceResponse(
            new RoleResource($role->load('permissions')),
            'Role updated successfully.'
        );
    }

    /**
     * Remove the specified role (Soft Delete).
     */
    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return $this->resourceResponse(new RoleResource($role), 'Role deleted successfully.');
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(Role $role): JsonResponse
    {
        $role = $this->roleService->toggleStatus($role);

        return $this->resourceResponse(
            new RoleResource($role->load('permissions')),
            'Role status toggled successfully.'
        );
    }

    /**
     * Sync permissions to the specified role (Replace existing).
     */
    public function syncPermissions(AssignPermissionRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->syncPermissions($role, $request->validated()['permissions']);

        return $this->resourceResponse(
            new RoleResource($role->load('permissions')),
            'Permissions synced successfully.'
        );
    }

    /**
     * Give permissions to the specified role (Additive).
     */
    public function givePermissions(AssignPermissionRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->givePermissions($role, $request->validated()['permissions']);

        return $this->resourceResponse(
            new RoleResource($role->load('permissions')),
            'Permissions given successfully.'
        );
    }

    /**
     * Revoke permissions from the specified role.
     */
    public function revokePermissions(AssignPermissionRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->revokePermissions($role, $request->validated()['permissions']);

        return $this->resourceResponse(
            new RoleResource($role->load('permissions')),
            'Permissions revoked successfully.'
        );
    }
}
