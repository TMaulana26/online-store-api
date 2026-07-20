<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

trait HandlesApiResponses
{
    /**
     * Return a standardized JSON success response.
     */
    protected function successResponse(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    /**
     * Return a standardized JSON error response.
     */
    protected function errorResponse(string $message = 'Error', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Return a resource response.
     */
    protected function resourceResponse(mixed $resource, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource,
            'errors' => null,
        ], $status);
    }

    /**
     * Return a paginated response, keeping pagination links and meta separated or encapsulated.
     */
    protected function paginatedResponse(mixed $resource, string $message = 'Success', int $status = 200): JsonResponse
    {
        if ($resource instanceof AnonymousResourceCollection) {
            $response = $resource->toResponse(request())->getData(true);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $response['data'] ?? $response,
                'meta' => $response['meta'] ?? null,
                'links' => $response['links'] ?? null,
                'errors' => null,
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $resource,
            'errors' => null,
        ], $status);
    }
}
