<?php

namespace Modules\Acl\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
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
            'menu' => $this->menu,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])),
        ];
    }
}
