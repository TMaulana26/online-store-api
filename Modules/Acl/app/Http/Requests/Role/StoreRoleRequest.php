<?php

namespace Modules\Acl\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array|nullable',
            'permissions.*' => 'exists:permissions,name',
        ];
    }
}
