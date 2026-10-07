<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_product_and_quote_never_expose_supplier_costs_or_credentials(): void
    {
        $supplier = Supplier::create(['name' => 'Private supplier', 'api_key' => 'private-test-key']);
        $product = $this->product(['supplier_id' => $supplier->id, 'supplier_url' => 'https://supplier.example.test/private', 'supplier_product_id' => 'private-source']);
        $this->assertNotSame('private-test-key', DB::table('suppliers')->value('api_key'));
        $this->assertSame('private-test-key', $supplier->fresh()->api_key);

        $productResponse = $this->getJson('/api/v1/products/'.$product->slug)->assertOk();
        $quoteResponse = $this->postJson('/api/v1/checkout/preview', ['items' => $this->items($product)])->assertOk();
        foreach ([$productResponse, $quoteResponse] as $response) {
            foreach (['purchase_price', 'supplier_url', 'supplier_id', 'supplier_product_id', 'api_key', '_snapshots', '_coupon'] as $field) {
                $this->assertStringNotContainsString('"'.$field.'"', $response->getContent());
            }
        }
    }

    public function test_client_cannot_set_prices_totals_owner_or_privileged_states(): void
    {
        $product = $this->product();
        foreach (['total' => 1, 'purchase_price' => 1, 'selling_price' => 1, 'discount_total' => 999999, 'shipping_total' => 0, 'user_id' => 1, 'payment_status' => 'paid', 'order_status' => 'delivered', 'admin_note' => 'injected'] as $field => $value) {
            $this->postJson('/api/v1/checkout/preview', ['items' => $this->items($product), $field => $value])
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/api/v1/checkout/preview', [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'selling_price' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_invalid_quantities_and_duplicate_lines_cannot_bypass_stock_limits(): void
    {
        $product = $this->product(['stock' => 1]);
        foreach ([0, -1, 21, 1.5, '1 OR 1=1'] as $quantity) {
            $this->postJson('/api/v1/checkout/preview', ['items' => [['product_id' => $product->id, 'quantity' => $quantity]]])
                ->assertUnprocessable();
        }
        $this->postJson('/api/v1/checkout/preview', ['items' => [...$this->items($product), ...$this->items($product)]])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertSame(1, $product->fresh()->stock);
    }

    public function test_variant_must_belong_to_requested_product(): void
    {
        $product = $this->product();
        $other = $this->product();
        $variant = ProductVariant::create(['product_id' => $other->id, 'name' => 'Other option', 'sku' => 'OPTION', 'selling_price' => 1, 'purchase_price' => 0, 'stock' => 10]);
        $this->postJson('/api/v1/checkout/preview', [
            'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_unpublished_and_low_margin_products_cannot_be_bought(): void
    {
        foreach ([['status' => 'draft'], ['margin_flagged' => true], ['minimum_margin' => 3000]] as $attributes) {
            $product = $this->product($attributes);
            $this->postJson('/api/v1/checkout/preview', ['items' => $this->items($product)])->assertUnprocessable();
        }
    }

    public function test_inactive_expired_future_minimum_and_exhausted_coupons_are_rejected(): void
    {
        $product = $this->product();
        foreach ([['active' => false], ['ends_at' => now()->subMinute()], ['starts_at' => now()->addDay()], ['minimum_order' => 999999], ['usage_limit' => 1, 'usage_count' => 1]] as $attributes) {
            $coupon = Coupon::create(['code' => strtoupper(Str::random(10)), 'type' => 'fixed', 'amount' => 100, ...$attributes]);
            $this->postJson('/api/v1/checkout/preview', ['items' => $this->items($product), 'coupon_code' => $coupon->code])
                ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
        }
        $this->postJson('/api/v1/checkout/preview', ['items' => $this->items($product), 'coupon_code' => 'NOTREAL'])
            ->assertUnprocessable()->assertJsonValidationErrors('coupon_code');
    }

    public function test_fixed_coupon_is_capped_at_subtotal_and_preview_does_not_redeem_it(): void
    {
        $product = $this->product();
        $coupon = Coupon::create(['code' => 'BIGDISCOUNT', 'type' => 'fixed', 'amount' => 100000]);
        $this->postJson('/api/v1/checkout/preview', ['items' => $this->items($product), 'coupon_code' => $coupon->code])
            ->assertOk()->assertJsonPath('data.discount_total', $product->selling_price)
            ->assertJsonPath('data.total', config('store.shipping_cents'));
        $this->assertSame(0, $coupon->fresh()->usage_count);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_order_creation_is_authoritative_and_identical_retry_reserves_stock_once(): void
    {
        $product = $this->product();
        $coupon = Coupon::create(['code' => 'ONCE', 'type' => 'percentage', 'amount' => 10, 'usage_limit' => 1]);
        $payload = [...$this->payload($product), 'coupon_code' => 'ONCE'];
        $key = (string) Str::uuid();
        $first = $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => $key])->assertCreated();
        $retry = $this->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => $key])->assertOk();
        $this->assertSame($first->json(), $retry->json());
        $first->assertJsonPath('data.total', 2190)->assertJsonPath('data.payment_status', 'unpaid');
        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(1, $coupon->fresh()->usage_count);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_idempotency_key_cannot_replay_changed_payload_or_another_customers_order(): void
    {
        $product = $this->product();
        $payload = $this->payload($product);
        $key = (string) Str::uuid();
        $firstCustomer = User::factory()->create();
        $this->actingAs($firstCustomer)->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => $key])->assertCreated();
        $this->postJson('/api/v1/orders', [...$payload, 'customer_name' => 'Changed'], ['Idempotency-Key' => $key])->assertConflict();
        Auth::forgetGuards();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/orders', $payload, ['Idempotency-Key' => $key])->assertConflict();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_order_policy_denies_other_customers_and_guests_and_no_public_order_lookup_exists(): void
    {
        $owner = User::factory()->create();
        [$order] = app(CheckoutService::class)->create($this->payload($this->product()), (string) Str::uuid(), $owner->id);
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue(Gate::forUser($owner)->allows('view', $order));
        $this->assertFalse(Gate::forUser($other)->allows('view', $order));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $order));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $order));
        $this->getJson('/api/v1/orders/'.$order->id)->assertNotFound();
    }

    public function test_order_submission_requires_uuid_idempotency_and_supported_payment_and_country(): void
    {
        $payload = $this->payload($this->product());
        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->postJson('/api/v1/orders', [...$payload, 'payment_method' => 'browser_paid', 'shipping_country' => 'US'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors(['payment_method', 'shipping_country']);
        $this->assertSame(0, Order::count());
    }

    public function test_order_rate_limit_applies_to_malformed_guest_requests(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/orders', [])->assertUnprocessable();
        }

        $this->postJson('/api/v1/orders', [])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_untrusted_forwarded_address_does_not_bypass_order_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('X-Forwarded-For', '192.0.2.'.($attempt + 1))
                ->postJson('/api/v1/orders', [])->assertUnprocessable();
        }

        $this->withHeader('X-Forwarded-For', '192.0.2.99')
            ->postJson('/api/v1/orders', [])->assertTooManyRequests();
    }

    private function product(array $attributes = []): Product
    {
        $unique = strtolower(Str::random(10));
        $category = Category::create(['name' => 'Category', 'slug' => 'category-'.$unique]);

        return Product::create([
            'category_id' => $category->id, 'name' => 'Product', 'slug' => 'product-'.$unique, 'sku' => 'SKU-'.$unique,
            'purchase_price' => 500, 'selling_price' => 2000, 'stock' => 10, 'track_stock' => true, 'status' => 'active', ...$attributes,
        ]);
    }

    private function items(Product $product): array
    {
        return [['product_id' => $product->id, 'quantity' => 1]];
    }

    private function payload(Product $product): array
    {
        return [
            'items' => $this->items($product), 'customer_name' => 'Customer Test', 'customer_email' => 'buyer@example.test',
            'customer_phone' => '+38267123456', 'shipping_address' => 'Test Street 1', 'shipping_city' => 'Podgorica',
            'shipping_postal_code' => '81000', 'shipping_country' => 'ME', 'payment_method' => 'cash_on_delivery',
        ];
    }
}
