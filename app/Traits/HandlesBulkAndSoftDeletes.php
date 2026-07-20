<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesBulkAndSoftDeletes
{
    /**
     * Restore the specified soft-deleted resource.
     */
    public function restore(string $id): JsonResponse
    {
        $service = $this->getService();
        $resourceClass = $this->getResourceClass();
        $modelName = $this->getModelName();

        $resource = $service->restore($id);

        return $this->resourceResponse(
            new $resourceClass($resource),
            ucfirst($modelName).' restored successfully.'
        );
    }

    /**
     * Force delete the specified resource.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $service = $this->getService();
        $resourceClass = $this->getResourceClass();
        $modelName = $this->getModelName();

        $resource = $service->forceDelete($id);

        return $this->resourceResponse(
            new $resourceClass($resource),
            ucfirst($modelName).' permanently deleted.'
        );
    }

    /**
     * Bulk soft delete resources.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required'],
        ]);

        $service = $this->getService();
        $resourceClass = $this->getResourceClass();
        $modelName = $this->getModelName();

        $result = $service->handleBulkOperation($request->input('ids'), 'delete');
        $affected = $result['affected'];
        $failedIds = $result['failed_ids'];

        return $this->successResponse([
            'affected' => $resourceClass::collection($affected),
            'failed_ids' => $failedIds,
        ], ucfirst($modelName).'s bulk deleted.');
    }

    /**
     * Bulk restore soft-deleted resources.
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required'],
        ]);

        $service = $this->getService();
        $resourceClass = $this->getResourceClass();
        $modelName = $this->getModelName();

        $result = $service->handleBulkOperation($request->input('ids'), 'restore');
        $affected = $result['affected'];
        $failedIds = $result['failed_ids'];

        return $this->successResponse([
            'affected' => $resourceClass::collection($affected),
            'failed_ids' => $failedIds,
        ], ucfirst($modelName).'s bulk restored.');
    }

    /**
     * Bulk force delete resources.
     */
    public function bulkForceDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required'],
        ]);

        $service = $this->getService();
        $resourceClass = $this->getResourceClass();
        $modelName = $this->getModelName();

        $result = $service->handleBulkOperation($request->input('ids'), 'forceDelete');
        $affected = $result['affected'];
        $failedIds = $result['failed_ids'];

        return $this->successResponse([
            'affected' => $resourceClass::collection($affected),
            'failed_ids' => $failedIds,
        ], ucfirst($modelName).'s bulk permanently deleted.');
    }

    /**
     * Bulk toggle active status of resources.
     */
    public function bulkToggleStatus(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['required'],
        ]);

        $service = $this->getService();
        $resourceClass = $this->getResourceClass();
        $modelName = $this->getModelName();

        $result = $service->handleBulkOperation($request->input('ids'), 'toggle');
        $affected = $result['affected'];
        $failedIds = $result['failed_ids'];

        return $this->successResponse([
            'affected' => $resourceClass::collection($affected),
            'failed_ids' => $failedIds,
        ], ucfirst($modelName).'s bulk status toggled.');
    }
}
