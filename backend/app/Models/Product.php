<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['specifications' => 'array', 'track_stock' => 'boolean', 'is_featured' => 'boolean', 'is_new' => 'boolean', 'margin_flagged' => 'boolean', 'disable_on_low_margin' => 'boolean', 'selling_price' => 'integer', 'purchase_price' => 'integer', 'shipping_cost' => 'integer', 'stock' => 'integer'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereHas('category', fn (Builder $q) => $q->where('is_active', true));
    }

    public function canPurchase(): bool
    {
        if ($this->status !== 'active' || ! $this->category?->is_active || ($this->margin_flagged && $this->disable_on_low_margin)) {
            return false;
        }
        $options = $this->variants->isNotEmpty() ? $this->variants : collect([$this]);

        return $options->contains(fn ($v) => (! $this->track_stock || $v->stock > 0) && (! $this->disable_on_low_margin || $v->selling_price - $v->purchase_price - $this->shipping_cost >= $this->minimum_margin));
    }

    public function canPurchaseVariant(ProductVariant $variant): bool
    {
        return $variant->product_id === $this->id && $this->status === 'active' && (bool) $this->category?->is_active
            && (! $this->track_stock || $variant->stock > 0)
            && (! $this->disable_on_low_margin || (! $this->margin_flagged && $variant->selling_price - $variant->purchase_price - $this->shipping_cost >= $this->minimum_margin));
    }

    public function getGrossProfitAttribute(): int
    {
        return $this->selling_price - $this->purchase_price - $this->shipping_cost;
    }

    public function getGrossMarginAttribute(): string
    {
        return $this->selling_price > 0 ? number_format($this->gross_profit * 100 / $this->selling_price, 2).'%' : '0%';
    }
}
