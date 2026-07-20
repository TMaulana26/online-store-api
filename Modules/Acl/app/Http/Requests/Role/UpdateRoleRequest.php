<?php

namespace Modules\Acl\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $id = $role instanceof Role ? $role->id : $role;

        return [
            'name' => 'sometimes|string|unique:roles,name,'.$id,
            'permissions' => 'array|nullable',
            'permissions.*' => 'exists:permissions,name',
        ];
    }
}
