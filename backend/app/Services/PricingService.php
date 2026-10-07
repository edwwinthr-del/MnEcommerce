<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class PricingService
{
    /** Caller must wrap locking calls in a transaction. */
    public function quote(array $input, bool $lock = false): array
    {
        $rows = collect($input['items'])->sortBy(fn ($i) => sprintf('%020d-%020d', $i['product_id'], $i['variant_id'] ?? 0))->values();
        $ids = $rows->pluck('product_id')->unique()->sort()->values();
        $query = Product::query()->with('category')->withCount('variants')->whereIn('id', $ids)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $products = $query->get()->keyBy('id');
        $variantIds = $rows->pluck('variant_id')->filter()->unique()->sort()->values();
        $variantsQuery = ProductVariant::query()->whereIn('id', $variantIds)->orderBy('id');
        if ($lock) {
            $variantsQuery->lockForUpdate();
        }
        $variants = $variantsQuery->get()->keyBy('id');
        $items = [];
        $snapshots = [];
        $subtotal = 0;
        $seen = [];
        foreach ($rows as $row) {
            $product = $products->get($row['product_id']);
            $variant = isset($row['variant_id']) ? $variants->get($row['variant_id']) : null;
            $key = $row['product_id'].':'.($row['variant_id'] ?? 0);
            if (isset($seen[$key])) {
                $this->invalid('items', 'Duplicate cart lines are not allowed.');
            }
            $seen[$key] = true;
            if (! $product || $product->status !== 'active' || ! $product->category?->is_active) {
                $this->invalid('items', 'A product is no longer available.');
            }
            if (isset($row['variant_id']) && (! $variant || $variant->product_id !== $product->id)) {
                $this->invalid('items', 'Invalid product option.');
            }
            if (! $variant && $product->variants_count > 0) {
                $this->invalid('items', 'Choose a product option.');
            }
            $inventory = $variant ?? $product;
            if (($product->margin_flagged && $product->disable_on_low_margin) || ($product->disable_on_low_margin && $inventory->selling_price - $inventory->purchase_price - $product->shipping_cost < $product->minimum_margin)) {
                $this->invalid('items', 'A product is temporarily unavailable.');
            }
            $quantity = (int) $row['quantity'];
            if ($quantity < 1 || $quantity > 20) {
                $this->invalid('items', 'Quantity must be between 1 and 20.');
            }
            if ($product->track_stock && $inventory->stock < $quantity) {
                $this->invalid('items', 'Insufficient stock for '.$product->name.'.');
            }
            $price = $inventory->selling_price;
            $line = ['product_id' => $product->id, 'variant_id' => $variant?->id, 'product_name' => $product->name.($variant ? ' — '.$variant->name : ''), 'sku' => $inventory->sku, 'quantity' => $quantity, 'selling_price' => $price, 'line_total' => $price * $quantity];
            $items[] = $line;
            $subtotal += $line['line_total'];
            $snapshots[] = array_diff_key($line, ['line_total' => true]) + ['purchase_price' => $inventory->purchase_price, 'shipping_cost' => $product->shipping_cost, 'supplier_id' => $product->supplier_id, 'supplier_url' => $product->supplier_url, 'stock_reserved' => $product->track_stock, 'reserved_variant' => $variant !== null];
        }
        $coupon = null;
        $discount = 0;
        if (! empty($input['coupon_code'])) {
            $couponQuery = Coupon::query()->where('code', strtoupper(trim($input['coupon_code'])));
            if ($lock) {
                $couponQuery->lockForUpdate();
            }
            $coupon = $couponQuery->first();
            if (! $coupon || ! $coupon->active || ($coupon->starts_at && $coupon->starts_at->isFuture()) || ($coupon->ends_at && $coupon->ends_at->isPast()) || ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) || $subtotal < $coupon->minimum_order) {
                $this->invalid('coupon_code', 'This coupon is invalid, expired, exhausted, or its minimum spend is not met.');
            }
            $discount = min($subtotal, $coupon->type === 'percentage' ? intdiv($subtotal * $coupon->amount, 100) : $coupon->amount);
        }
        $shipping = $subtotal - $discount >= config('store.free_shipping_threshold_cents') ? 0 : config('store.shipping_cents');

        return ['subtotal' => $subtotal, 'shipping_total' => $shipping, 'discount_total' => $discount, 'total' => $subtotal + $shipping - $discount, 'currency' => 'EUR', 'items' => $items, 'coupon_code' => $coupon?->code, '_snapshots' => $snapshots, '_coupon' => $coupon];
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function publicQuote(array $quote): array
    {
        unset($quote['_snapshots'],$quote['_coupon']);

        return $quote;
    }
}
