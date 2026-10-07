<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $hidden = ['purchase_price', 'shipping_cost', 'supplier_url', 'supplier_id'];

    protected function casts(): array
    {
        return ['stock_reserved' => 'boolean', 'reserved_variant' => 'boolean', 'quantity' => 'integer', 'purchase_price' => 'integer', 'selling_price' => 'integer', 'shipping_cost' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
