<?php

namespace Modules\Acl\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'translations' => $this->when($request->boolean('with_translations'), $this->getTranslations()),
            'guard_name' => $this->guard_name,
            'users' => $this->whenLoaded('users', fn () => $this->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $permission->display_name,
            ])),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
