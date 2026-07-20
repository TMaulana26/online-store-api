<?php

declare(strict_types=1);

namespace Modules\Store\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'stock' => $this->stock,
            'price' => $this->price,
            'flash_sale_price' => $this->flash_sale_price,
            'flash_sale_start' => $this->flash_sale_start?->toIso8601String(),
            'flash_sale_end' => $this->flash_sale_end?->toIso8601String(),
            'is_in_flash_sale' => $this->isInFlashSale(),
            'active_price' => $this->getActivePrice(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
