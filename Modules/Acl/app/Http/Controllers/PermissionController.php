<?php

namespace Modules\Acl\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\HandlesBulkAndSoftDeletes;
use Illuminate\Http\JsonResponse;
use Modules\Acl\Http\Requests\Permission\AssignRoleRequest;
use Modules\Acl\Http\Requests\Permission\IndexPermissionRequest;
use Modules\Acl\Http\Requests\Permission\StorePermissionRequest;
use Modules\Acl\Http\Requests\Permission\UpdatePermissionRequest;
use Modules\Acl\Models\Permission;
use Modules\Acl\Services\PermissionService;
use Modules\Acl\Transformers\PermissionResource;

class PermissionController extends Controller
{
    use HandlesBulkAndSoftDeletes;

    public function __construct(
        protected PermissionService $permissionService
    ) {}

    protected function getService()
    {
        return $this->permissionService;
    }

    protected function getResourceClass(): string
    {
        return PermissionResource::class;
    }

    protected function getModelName(): string
    {
        return 'permission';
    }

    protected function getEagerLoadRelations(): array
    {
        return ['roles'];
    }

    /**
     * Display a listing of permissions.
     */
    public function index(IndexPermissionRequest $request): JsonResponse
    {
        $permissions = $this->permissionService->index($request->validated());

        if ($request->input('with_roles', false)) {
            $permissions->load('roles');
        }

        return $this->paginatedResponse(
            PermissionResource::collection($permissions),
            'Permissions retrieved successfully.'
        );
    }

    /**
     * Store a newly created permission.
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        $permission = $this->permissionService->store($request->validated());

        return $this->resourceResponse(
            new PermissionResource($permission),
            'Permission created successfully.',
            201
        );
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission): JsonResponse
    {
        return $this->resourceResponse(
            new PermissionResource($permission->load('roles')),
            'Permission details retrieved successfully.'
        );
    }

    /**
     * Update the specified permission.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->permissionService->update($permission, $request->validated());

        return $this->resourceResponse(
            new PermissionResource($permission->load('roles')),
            'Permission updated successfully.'
        );
    }

    /**
     * Remove the specified permission (Soft Delete).
     */
    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return $this->resourceResponse(new PermissionResource($permission), 'Permission deleted successfully.');
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(Permission $permission): JsonResponse
    {
        $permission = $this->permissionService->toggleStatus($permission);

        return $this->resourceResponse(
            new PermissionResource($permission->load('roles')),
            'Permission status toggled successfully.'
        );
    }

    /**
     * Sync roles to the specified permission (Replace existing).
     */
    public function syncRoles(AssignRoleRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->permissionService->syncRoles($permission, $request->validated()['roles']);

        return $this->resourceResponse(
            new PermissionResource($permission->load('roles')),
            'Roles synced successfully.'
        );
    }

    /**
     * Assign roles to the specified permission (Additive).
     */
    public function assignRoles(AssignRoleRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->permissionService->assignRoles($permission, $request->validated()['roles']);

        return $this->resourceResponse(
            new PermissionResource($permission->load('roles')),
            'Roles assigned successfully.'
        );
    }

    /**
     * Remove roles from the specified permission.
     */
    public function removeRoles(AssignRoleRequest $request, Permission $permission): JsonResponse
    {
        $permission = $this->permissionService->removeRoles($permission, $request->validated()['roles']);

        return $this->resourceResponse(
            new PermissionResource($permission->load('roles')),
            'Roles removed successfully.'
        );
    }
}
