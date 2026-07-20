<?php

namespace Modules\Acl\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class IndexRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('with_permissions')) {
            $this->merge([
                'with_permissions' => filter_var($this->with_permissions, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                'with_users' => filter_var($this->with_users, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'trashed' => 'in:only,with|nullable',
            'per_page' => 'integer|min:-1|max:1000|nullable',
            'sort_by' => 'string|in:id,name,guard_name,created_at|nullable',
            'sort_order' => 'in:asc,desc|nullable',
            'search' => 'string|nullable|max:255',
            'status' => 'in:active,inactive|nullable',
            'with_permissions' => 'boolean|nullable',
            'with_users' => 'boolean|nullable',
        ];
    }
}
