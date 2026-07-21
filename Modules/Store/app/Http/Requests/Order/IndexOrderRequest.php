<?php

declare(strict_types=1);

namespace Modules\Store\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class IndexOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'trashed' => 'in:only,with|nullable',
            'per_page' => 'integer|min:-1|max:1000|nullable',
            'sort_by' => 'string|in:id,status,total_amount,created_at,is_active|nullable',
            'sort_order' => 'in:asc,desc|nullable',
            'search' => 'string|nullable|max:255',
            'status' => 'string|nullable|max:255',
        ];
    }
}
