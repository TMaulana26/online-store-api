<?php

namespace Modules\Acl\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Permission;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permission = $this->route('permission');
        $id = $permission instanceof Permission ? $permission->id : $permission;

        return [
            'name' => 'sometimes|string|unique:permissions,name,'.$id,
            'menu' => 'nullable|string|max:255',
        ];
    }
}
