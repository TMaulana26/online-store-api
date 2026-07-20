<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HandlesIndexQuery
{
    /**
     * Handle standard search, sort, and pagination for list endpoints.
     */
    protected function handleIndexQuery(Builder $query, array $params, array $searchableFields = [])
    {
        // Search
        if (! empty($params['search']) && ! empty($searchableFields)) {
            $search = $params['search'];
            $query->where(function (Builder $q) use ($search, $searchableFields) {
                foreach ($searchableFields as $index => $field) {
                    if ($index === 0) {
                        $q->where($field, 'like', "%{$search}%");
                    } else {
                        $q->orWhere($field, 'like', "%{$search}%");
                    }
                }
            });
        }

        // Sorting
        $sortBy = $params['sort_by'] ?? 'id';
        $sortOrder = $params['sort_order'] ?? 'desc';

        // Security check for order direction
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc'], true) ? $sortOrder : 'desc';

        $query->orderBy($sortBy, $sortOrder);

        // Pagination / Limit
        $perPage = isset($params['per_page']) ? (int) $params['per_page'] : 15;

        if ($perPage === -1) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }
}
