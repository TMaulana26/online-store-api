<?php

namespace Modules\Acl\Services;

use App\Traits\HandlesIndexQuery;
use Illuminate\Support\Facades\DB;
use Modules\Acl\Models\User;

class UserService
{
    use HandlesIndexQuery;

    /**
     * Find a user by its ID.
     */
    public function findById(string $id): User
    {
        return User::findOrFail($id);
    }

    /**
     * Display a listing of the users resource.
     */
    public function index(array $params)
    {
        return $this->handleIndexQuery(
            User::query(),
            $params,
            ['name', 'email']
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create($data);

            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            return $user->refresh();
        });
    }

    /**
     * Update the specified user resource in storage.
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update($data);

            if (isset($data['email']) && $data['email'] !== $user->getOriginal('email')) {
                $user->email_verified_at = null;
                $user->save();
            }

            if (isset($data['roles'])) {
                $user->syncRoles($data['roles']);
            }

            return $user->refresh();
        });
    }

    /**
     * Remove the specified user resource from storage (Soft Delete).
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Sync roles to a user (Replace existing roles).
     */
    public function syncRoles(User $user, array $roles): User
    {
        return DB::transaction(function () use ($user, $roles) {
            $user->syncRoles($roles);

            return $user->refresh();
        });
    }

    /**
     * Assign roles to a user (Additive).
     */
    public function assignRoles(User $user, array $roles): User
    {
        return DB::transaction(function () use ($user, $roles) {
            $user->assignRole($roles);

            return $user->refresh();
        });
    }

    /**
     * Remove roles from a user.
     */
    public function removeRoles(User $user, array $roles): User
    {
        return DB::transaction(function () use ($user, $roles) {
            foreach ($roles as $role) {
                $user->removeRole($role);
            }

            return $user->refresh();
        });
    }

    /**
     * Perform bulk operations on users.
     *
     * @param  string  $operation  (delete|restore|forceDelete|toggle)
     */
    public function handleBulkOperation(array $ids, string $operation): array
    {
        return DB::transaction(function () use ($ids, $operation) {
            $query = match ($operation) {
                'delete',
                'toggle' => User::query(),
                'restore',
                'forceDelete' => User::onlyTrashed(),
                default => throw new \InvalidArgumentException("Invalid operation: {$operation}"),
            };

            $users = $query->whereIn('id', $ids)->get();
            $foundIds = $users->pluck('id')->toArray();
            $notFoundIds = array_values(array_diff($ids, $foundIds));

            if ($users->isNotEmpty()) {
                match ($operation) {
                    'delete' => User::whereIn('id', $foundIds)->delete(),
                    'restore' => User::onlyTrashed()->whereIn('id', $foundIds)->restore(),
                    'forceDelete' => User::onlyTrashed()->whereIn('id', $foundIds)->forceDelete(),
                    'toggle' => $users->each(fn ($u) => $u->update(['is_active' => ! $u->is_active])),
                };

                // Refresh models to reflect changes (e.g. deleted_at, is_active)
                if ($operation !== 'forceDelete') {
                    $users->each->refresh();
                }
            }

            return [
                'affected' => $users,
                'failed_ids' => $notFoundIds,
            ];
        });
    }

    /**
     * Toggle a single user's activity status.
     */
    public function toggleStatus(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user->update(['is_active' => ! $user->is_active]);

            return $user->refresh();
        });
    }

    /**
     * Restore a single user.
     */
    public function restore(string $id): User
    {
        return DB::transaction(function () use ($id) {
            $user = User::onlyTrashed()->findOrFail($id);
            $user->restore();

            return $user->refresh();
        });
    }

    /**
     * Force delete a single user.
     */
    public function forceDelete(string $id): User
    {
        return DB::transaction(function () use ($id) {
            $user = User::onlyTrashed()->findOrFail($id);
            // We clone or store data before deletion to return it in the resource if needed
            $userData = clone $user;
            $user->forceDelete();

            return $userData;
        });
    }
}
