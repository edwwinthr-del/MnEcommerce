<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\LowMarginNotification;
use App\Services\AnalyticsService;
use App\Services\CheckoutService;
use App\Services\MarginProtectionService;
use App\Services\OrderWorkflow;
use App\Services\SupplierOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_releases_reserved_inventory_and_coupon_exactly_once(): void
    {
        $product = $this->product();
        $coupon = Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'amount' => 100]);
        [$order] = app(CheckoutService::class)->create($this->payload($product) + ['coupon_code' => $coupon->code], (string) Str::uuid(), null);
        $this->assertSame(4, $product->fresh()->stock);
        $workflow = app(OrderWorkflow::class);
        $workflow->transition($order, 'cancelled', null);
        $workflow->transition($order, 'cancelled', null);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(0, $coupon->fresh()->usage_count);
        $this->assertNotNull($order->fresh()->inventory_released_at);
        $this->assertSame(1, AuditLog::where('action', 'order.status_changed')->count());
    }

    public function test_cancelling_order_for_deleted_variant_does_not_add_stock_to_parent(): void
    {
        $product = $this->product();
        $variant = $this->variant($product);
        $payload = $this->payload($product);
        $payload['items'][0]['variant_id'] = $variant->id;
        [$order] = app(CheckoutService::class)->create($payload, (string) Str::uuid(), null);
        $this->assertTrue($order->items->first()->reserved_variant);
        $variant->delete();
        app(OrderWorkflow::class)->transition($order, 'cancelled', null);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_bank_transfer_requires_verified_payment_and_refunds_do_not_restock_without_return(): void
    {
        $product = $this->product();
        $payload = $this->payload($product);
        $payload['payment_method'] = 'bank_transfer';
        [$order] = app(CheckoutService::class)->create($payload, (string) Str::uuid(), null);
        $workflow = app(OrderWorkflow::class);
        try {
            $workflow->transition($order, 'processing', null);
            $this->fail('Unpaid bank transfer advanced to fulfillment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order_status', $exception->errors());
        }
        $workflow->recordPayment($order, null);
        foreach (['processing', 'shipped', 'delivered', 'refunded'] as $status) {
            $workflow->transition($order, $status, null);
        }
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(0, app(AnalyticsService::class)->report()['orders']);
    }

    public function test_completed_order_analytics_keep_purchase_snapshots_after_catalog_cost_changes(): void
    {
        $product = $this->product(['shipping_cost' => 150]);
        [$order] = app(CheckoutService::class)->create($this->payload($product), (string) Str::uuid(), null);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            app(OrderWorkflow::class)->transition($order, $status, null);
        }
        app(MarginProtectionService::class)->updateCosts($product, 1800, 400);
        $report = app(AnalyticsService::class)->report(now()->toDateString(), now()->toDateString());
        $this->assertSame(1, $report['orders']);
        $this->assertSame($order->total, $report['revenue']);
        $this->assertSame(500, $report['cogs']);
        $this->assertSame(150, $report['supplier_shipping']);
        $this->assertSame($order->total - 650, $report['estimated_gross_profit']);
        $this->assertSame(0, app(AnalyticsService::class)->report(now()->addDay()->toDateString())['orders']);
    }

    public function test_margin_alert_is_deduplicated_and_resolves_without_changing_customer_price(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(['minimum_margin' => 500]);
        $service = app(MarginProtectionService::class);
        $service->updateCosts($product, 1700, 100, $admin->id);
        $service->updateCosts($product, 1800, 100, $admin->id);
        $this->assertTrue($product->fresh()->margin_flagged);
        $this->assertFalse($product->fresh()->canPurchase());
        Notification::assertSentToTimes($admin, LowMarginNotification::class, 1);
        $service->save($product, ['purchase_price' => 700], $admin->id);
        $this->assertFalse($product->fresh()->margin_flagged);
        $this->assertTrue($product->fresh()->canPurchase());
        $this->assertSame(2000, $product->fresh()->selling_price);
    }

    public function test_supplier_workflow_preserves_timestamps_and_can_cancel_after_customer_cancellation(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier']);
        $product = $this->product(['supplier_id' => $supplier->id]);
        [$order] = app(CheckoutService::class)->create($this->payload($product), (string) Str::uuid(), null);
        $service = app(SupplierOrderService::class);
        $data = ['order_id' => $order->id, 'supplier_id' => $supplier->id, 'cost' => 500, 'shipping_cost' => 100, 'status' => 'pending'];
        $record = $service->save(null, $data, null);
        $record = $service->save($record, [...$data, 'status' => 'ordered', 'ordered_at' => '2000-01-01'], null);
        $this->assertTrue($record->ordered_at->isToday());
        $orderedAt = $record->ordered_at->toISOString();
        $this->travel(1)->hours();
        $record = $service->save($record, [...$data, 'status' => 'ordered'], null);
        $this->assertSame($orderedAt, $record->ordered_at->toISOString());
        app(OrderWorkflow::class)->transition($order, 'cancelled', null);
        $record = $service->save($record, [...$data, 'status' => 'cancelled'], null);
        $this->assertSame('cancelled', $record->status);
    }

    public function test_supplier_orders_cannot_be_reassigned_or_fulfill_unpaid_bank_transfers(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier']);
        $product = $this->product(['supplier_id' => $supplier->id]);
        $payload = $this->payload($product);
        $payload['payment_method'] = 'bank_transfer';
        [$order] = app(CheckoutService::class)->create($payload, (string) Str::uuid(), null);
        [$other] = app(CheckoutService::class)->create($payload, (string) Str::uuid(), null);
        $service = app(SupplierOrderService::class);
        $data = ['order_id' => $order->id, 'supplier_id' => $supplier->id, 'cost' => 500, 'shipping_cost' => 0, 'status' => 'pending'];
        $record = $service->save(null, $data, null);
        foreach ([['order_id' => $other->id], ['status' => 'ordered']] as $change) {
            try {
                $service->save($record, [...$data, ...$change], null);
                $this->fail('Invalid supplier mutation was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame('pending', $record->fresh()->status);
        $this->assertSame($order->id, $record->fresh()->order_id);
    }

    public function test_public_variants_expose_purchasability_without_private_costs(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, ['purchase_price' => 1900, 'selling_price' => 1800]);
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()
            ->assertJsonPath('data.variants.0.id', $variant->id)
            ->assertJsonPath('data.variants.0.can_purchase', false)
            ->assertJsonMissingPath('data.variants.0.purchase_price');
    }

    private function product(array $attributes = []): Product
    {
        $unique = strtolower(Str::random(10));
        $category = Category::create(['name' => 'Domain test', 'slug' => $unique]);

        return Product::create(['category_id' => $category->id, 'name' => 'Test product', 'slug' => $unique, 'sku' => $unique, 'purchase_price' => 500, 'selling_price' => 2000, 'stock' => 5, 'track_stock' => true, 'status' => 'active', ...$attributes]);
    }

    private function variant(Product $product, array $attributes = []): ProductVariant
    {
        return ProductVariant::create(['product_id' => $product->id, 'name' => 'Variant', 'sku' => Str::random(10), 'purchase_price' => 500, 'selling_price' => 2000, 'stock' => 5, ...$attributes]);
    }

    private function payload(Product $product): array
    {
        return ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.test', 'customer_phone' => '+38267123456', 'shipping_address' => 'Test Street 1', 'shipping_city' => 'Podgorica', 'shipping_postal_code' => '81000', 'shipping_country' => 'ME', 'payment_method' => 'cash_on_delivery'];
    }
}
