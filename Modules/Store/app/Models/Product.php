<?php

declare(strict_types=1);

namespace Modules\Store\Models;

use App\Traits\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Store\Database\Factories\ProductFactory;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasActiveStatus, HasFactory, HasTranslations, SoftDeletes;

    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    protected $fillable = [
        'name',
        'description',
        'stock',
        'price',
        'flash_sale_price',
        'flash_sale_start',
        'flash_sale_end',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'stock' => 'integer',
        'price' => 'decimal:2',
        'flash_sale_price' => 'decimal:2',
        'flash_sale_start' => 'datetime',
        'flash_sale_end' => 'datetime',
    ];

    public array $translatable = ['name', 'description'];

    /**
     * Check if the product is currently in an active flash sale.
     */
    public function isInFlashSale(): bool
    {
        if (is_null($this->flash_sale_price)) {
            return false;
        }

        $now = now();

        return (! is_null($this->flash_sale_start) && $now->greaterThanOrEqualTo($this->flash_sale_start))
            && (! is_null($this->flash_sale_end) && $now->lessThanOrEqualTo($this->flash_sale_end));
    }

    /**
     * Get the active price of the product (flash sale price if active, fallback to regular price).
     */
    public function getActivePrice(): string
    {
        return $this->isInFlashSale() ? (string) $this->flash_sale_price : (string) $this->price;
    }

    /**
     * Get the order items for the product.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
