<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflow
{
    public const TRANSITIONS = [
        'pending' => ['paid', 'processing', 'cancelled'],
        'paid' => ['processing', 'refunded'],
        'processing' => ['ordered_from_supplier', 'shipped', 'cancelled', 'refunded'],
        'ordered_from_supplier' => ['shipped', 'cancelled', 'refunded'],
        'shipped' => ['delivered', 'refunded'],
        'delivered' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    public function transition(Order $order, string $status, ?int $actorId): Order
    {
        return DB::transaction(function () use ($order, $status, $actorId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $old = $order->order_status;
            if ($order->payment_method === 'bank_transfer' && $order->payment_status !== 'paid' && in_array($status, ['processing', 'ordered_from_supplier', 'shipped', 'delivered'], true)) {
                throw ValidationException::withMessages(['order_status' => 'Record verified bank payment before fulfillment.']);
            }
            if ($old === $status) {
                return $order;
            }
            if (! in_array($status, self::TRANSITIONS[$old] ?? [], true)) {
                throw ValidationException::withMessages(['order_status' => 'This order transition is not allowed.']);
            }
            if ($status === 'cancelled' && $order->payment_status === 'paid') {
                throw ValidationException::withMessages(['order_status' => 'Paid orders must be refunded.']);
            }
            if ($status === 'refunded' && $order->payment_status !== 'paid') {
                throw ValidationException::withMessages(['order_status' => 'Only a paid order can be marked refunded.']);
            }
            if ($status === 'cancelled' && ! $order->inventory_released_at) {
                foreach ($order->items()->orderBy('product_id')->orderBy('variant_id')->get() as $item) {
                    if (! $item->stock_reserved) {
                        continue;
                    }
                    if ($item->variant_id) {
                        ProductVariant::query()->whereKey($item->variant_id)->increment('stock', $item->quantity);
                    } elseif (! $item->reserved_variant && $item->product_id) {
                        Product::query()->whereKey($item->product_id)->increment('stock', $item->quantity);
                    }
                }
                if ($order->coupon_id) {
                    $order->coupon()->where('usage_count', '>', 0)->decrement('usage_count');
                }
                $order->inventory_released_at = now();
            }
            $order->order_status = $status;
            if ($status === 'paid' || ($status === 'delivered' && $order->payment_method === 'cash_on_delivery')) {
                $order->payment_status = 'paid';
            }
            if ($status === 'refunded') {
                $order->payment_status = 'refunded';
            }
            $order->save();
            AuditLog::create(['user_id' => $actorId, 'action' => 'order.status_changed', 'subject_type' => Order::class, 'subject_id' => $order->id, 'metadata' => ['from' => $old, 'to' => $status]]);

            return $order;
        }, 5);
    }

    public function recordPayment(Order $order, ?int $actorId): Order
    {
        return DB::transaction(function () use ($order, $actorId) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($order->order_status, ['cancelled', 'refunded'], true)) {
                throw ValidationException::withMessages(['payment_status' => 'Cannot collect payment for this order.']);
            }
            if ($order->payment_status === 'paid') {
                return $order;
            }
            $order->update(['payment_status' => 'paid']);
            AuditLog::create(['user_id' => $actorId, 'action' => 'order.payment_recorded', 'subject_type' => Order::class, 'subject_id' => $order->id, 'metadata' => ['payment_method' => $order->payment_method]]);

            return $order;
        }, 5);
    }
}
