<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'short_description' => $this->short_description, 'description' => $this->description, 'sku' => $this->sku, 'brand' => $this->brand,
            'selling_price' => $this->selling_price, 'old_price' => $this->old_price, 'stock' => $this->stock, 'track_stock' => $this->track_stock, 'is_featured' => $this->is_featured, 'is_new' => $this->is_new,
            'estimated_delivery_min' => $this->estimated_delivery_min, 'estimated_delivery_max' => $this->estimated_delivery_max, 'category' => $this->category->only(['id', 'name', 'slug']),
            'images' => $this->images->map(fn ($image) => $image->only(['id', 'image_url', 'is_primary', 'sort_order'])),
            'variants' => $this->variants->map(fn ($variant) => $variant->only(['id', 'name', 'sku', 'selling_price', 'stock']) + ['can_purchase' => $this->resource->canPurchaseVariant($variant)]),
            'specifications' => $this->specifications ?? (object) [], 'can_purchase' => $this->resource->canPurchase(),
            'seo_title' => $this->seo_title, 'seo_description' => $this->seo_description,
        ];
    }
}
