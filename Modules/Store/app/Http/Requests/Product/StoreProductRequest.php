<?php

declare(strict_types=1);

namespace Modules\Store\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'name' => 'required|array',
            'name.en' => 'required|string|max:255',
            'name.id' => 'required|string|max:255',
            'description' => 'nullable|array',
            'description.en' => 'nullable|string',
            'description.id' => 'nullable|string',
            'stock' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'flash_sale_price' => 'nullable|numeric|min:0',
            'flash_sale_start' => 'nullable|date',
            'flash_sale_end' => 'nullable|date|after_or_equal:flash_sale_start',
            'is_active' => 'boolean|nullable',
        ];
    }
}
