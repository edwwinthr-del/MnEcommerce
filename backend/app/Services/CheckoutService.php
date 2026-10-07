<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CheckoutService
{
    public function __construct(private PricingService $pricing) {}

    public function create(array $input, string $key, ?int $userId): array
    {
        $input['items'] = collect($input['items'])->sortBy(fn ($i) => $i['product_id'].':'.($i['variant_id'] ?? 0))->values()->all();
        $hash = hash('sha256', json_encode(['user_id' => $userId, 'input' => $this->canonicalize($input)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($input, $key, $userId, $hash) {
            // Serialize identical keys even before their order row exists. PostgreSQL releases this lock on commit/rollback.
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$key]);
            }
            $existing = Order::query()->where('idempotency_key', $key)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new ConflictHttpException('This idempotency key was already used for another request.');
                }

                return [$existing, false];
            }
            $quote = $this->pricing->quote($input, true);
            $order = Order::create(collect($input)->except(['items', 'coupon_code'])->all() + [
                'user_id' => $userId, 'order_number' => 'MORA-'.strtoupper((string) Str::ulid()), 'idempotency_key' => $key, 'request_hash' => $hash,
                'subtotal' => $quote['subtotal'], 'shipping_total' => $quote['shipping_total'], 'discount_total' => $quote['discount_total'], 'total' => $quote['total'], 'currency' => 'EUR',
                'coupon_id' => $quote['_coupon']?->id, 'order_status' => 'pending', 'payment_status' => 'unpaid',
            ]);
            foreach ($quote['_snapshots'] as $snapshot) {
                $order->items()->create($snapshot);
                if ($snapshot['stock_reserved']) {
                    $model = $snapshot['variant_id'] ? ProductVariant::query()->findOrFail($snapshot['variant_id']) : Product::query()->findOrFail($snapshot['product_id']);
                    $model->decrement('stock', $snapshot['quantity']);
                }
            }
            $quote['_coupon']?->increment('usage_count');
            AuditLog::create(['user_id' => $userId, 'action' => 'order.created', 'subject_type' => Order::class, 'subject_id' => $order->id, 'metadata' => ['order_number' => $order->order_number]]);

            return [$order, true];
        }, 5);
    }

    private function canonicalize(array $input): array
    {
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = $this->canonicalize($value);
            }
        } unset($value);
        ksort($input);

        return $input;
    }
}
