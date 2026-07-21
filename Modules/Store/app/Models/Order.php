<?php

declare(strict_types=1);

namespace Modules\Store\Models;

use App\Traits\HasActiveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Acl\Models\User;

class Order extends Model
{
    use HasActiveStatus, SoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'total_amount',
        'is_active',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that placed the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
