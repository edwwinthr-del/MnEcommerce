<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierOrderService
{
    public function save(?SupplierOrder $record, array $data, ?int $actorId): SupplierOrder
    {
        return DB::transaction(function () use ($record, $data, $actorId) {
            if ($record) {
                $record = SupplierOrder::query()->lockForUpdate()->findOrFail($record->id);
                if ((int) $data['order_id'] !== $record->order_id || (int) $data['supplier_id'] !== $record->supplier_id) {
                    throw ValidationException::withMessages(['order_id' => 'An existing supplier order cannot be reassigned.']);
                }
            }
            $old = $record?->status;
            $allowed = ['pending' => ['ordered', 'cancelled'], 'ordered' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'cancelled' => []];
            if (! array_key_exists($data['status'], $allowed) || ($old && $old !== $data['status'] && ! in_array($data['status'], $allowed[$old] ?? [], true))) {
                throw ValidationException::withMessages(['status' => 'Invalid supplier order transition.']);
            }
            if (! $record && $data['status'] !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Create the supplier order as pending first.']);
            }
            $order = Order::query()->lockForUpdate()->findOrFail($data['order_id']);
            if (in_array($order->order_status, ['cancelled', 'refunded'], true) && $data['status'] !== 'cancelled') {
                throw ValidationException::withMessages(['order_id' => 'Cannot fulfill a cancelled or refunded order.']);
            }
            if ($order->payment_method === 'bank_transfer' && $order->payment_status !== 'paid' && in_array($data['status'], ['ordered', 'shipped', 'delivered'], true)) {
                throw ValidationException::withMessages(['status' => 'Record verified bank payment before supplier fulfillment.']);
            }
            if (! $order->items()->where('supplier_id', $data['supplier_id'])->exists()) {
                throw ValidationException::withMessages(['supplier_id' => 'This supplier does not belong to an order item.']);
            }
            if ((! $record || $data['status'] === 'ordered') && ! Supplier::query()->whereKey($data['supplier_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['supplier_id' => 'This supplier is inactive.']);
            }
            unset($data['ordered_at'],$data['shipped_at'],$data['delivered_at']);
            foreach (['ordered', 'shipped', 'delivered'] as $status) {
                if ($data['status'] === $status) {
                    $data[$status.'_at'] = $record?->getAttribute($status.'_at') ?? now();
                }
            }
            if ($record) {
                $record->update($data);
            } else {
                $record = SupplierOrder::create($data);
            }
            AuditLog::create(['user_id' => $actorId, 'action' => 'supplier_order.saved', 'subject_type' => SupplierOrder::class, 'subject_id' => $record->id, 'metadata' => ['from' => $old, 'to' => $record->status]]);

            return $record;
        }, 5);
    }
}
