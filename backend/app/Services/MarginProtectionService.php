<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowMarginNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarginProtectionService
{
    public function updateCosts(Product $product, int $cost, int $shipping, ?int $actorId = null): Product
    {
        if ($cost < 0 || $shipping < 0) {
            throw ValidationException::withMessages(['purchase_price' => 'Supplier costs cannot be negative.']);
        }

        return $this->save($product, ['purchase_price' => $cost, 'shipping_cost' => $shipping], $actorId);
    }

    public function save(?Product $product, array $data, ?int $actorId = null): Product
    {
        return DB::transaction(function () use ($product, $data, $actorId) {
            $product = $product ? Product::query()->lockForUpdate()->findOrFail($product->id) : new Product;
            $wasFlagged = (bool) $product->margin_flagged;
            $product->fill($data);
            $flagged = $product->selling_price - $product->purchase_price - $product->shipping_cost < $product->minimum_margin;
            $product->margin_flagged = $flagged;
            $product->save();
            if ($flagged && ! $wasFlagged) {
                User::query()->where('role', 'admin')->each(fn ($admin) => $admin->notify(new LowMarginNotification($product)));
            }
            AuditLog::create(['user_id' => $actorId, 'action' => $flagged ? 'product.margin_alert' : 'product.cost_updated', 'subject_type' => Product::class, 'subject_id' => $product->id, 'metadata' => ['margin_flagged' => $flagged]]);

            return $product;
        }, 5);
    }
}
