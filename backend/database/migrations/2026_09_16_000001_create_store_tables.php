<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('role', 20)->default('customer')->index());
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('image')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('website')->nullable();
            $t->string('contact_name')->nullable();
            $t->string('email')->nullable();
            $t->string('api_url')->nullable();
            $t->text('api_key')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('sku')->unique();
            $t->string('brand')->nullable();
            $t->text('short_description')->nullable();
            $t->text('description')->nullable();
            $t->json('specifications')->nullable();
            $t->unsignedBigInteger('purchase_price');
            $t->unsignedBigInteger('selling_price');
            $t->unsignedBigInteger('old_price')->nullable();
            $t->unsignedBigInteger('shipping_cost')->default(0);
            $t->unsignedBigInteger('minimum_margin')->default(0);
            $t->boolean('margin_flagged')->default(false);
            $t->boolean('disable_on_low_margin')->default(true);
            $t->unsignedInteger('stock')->default(0);
            $t->boolean('track_stock')->default(true);
            $t->unsignedSmallInteger('estimated_delivery_min')->default(3);
            $t->unsignedSmallInteger('estimated_delivery_max')->default(7);
            $t->string('supplier_url', 2048)->nullable();
            $t->string('supplier_product_id')->nullable();
            $t->string('status', 20)->default('draft');
            $t->boolean('is_featured')->default(false);
            $t->boolean('is_new')->default(false);
            $t->string('seo_title')->nullable();
            $t->text('seo_description')->nullable();
            $t->timestamps();
            $t->index(['status', 'category_id']);
            $t->index(['status', 'is_featured']);
            $t->index(['status', 'selling_price']);
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('image_url', 2048);
            $t->integer('sort_order')->default(0);
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('sku')->unique();
            $t->unsignedBigInteger('purchase_price');
            $t->unsignedBigInteger('selling_price');
            $t->unsignedInteger('stock')->default(0);
            $t->string('supplier_variant_id')->nullable();
            $t->timestamps();
        });
        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('subtitle')->nullable();
            $t->string('image', 2048);
            $t->string('mobile_image', 2048)->nullable();
            $t->string('button_text')->nullable();
            $t->string('button_url')->nullable();
            $t->string('position')->default('hero');
            $t->integer('sort_order')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('type', 20);
            $t->unsignedBigInteger('amount');
            $t->unsignedBigInteger('minimum_order')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('usage_count')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('order_number')->unique();
            $t->uuid('idempotency_key')->unique();
            $t->string('request_hash', 64);
            $t->string('customer_name');
            $t->string('customer_email');
            $t->string('customer_phone', 40);
            $t->string('shipping_address');
            $t->string('shipping_city');
            $t->string('shipping_postal_code', 20);
            $t->string('shipping_country', 2)->default('ME');
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('shipping_total');
            $t->unsignedBigInteger('discount_total');
            $t->unsignedBigInteger('total');
            $t->string('currency', 3)->default('EUR');
            $t->string('payment_method', 30);
            $t->string('payment_status', 20)->default('unpaid');
            $t->string('order_status', 30)->default('pending');
            $t->text('customer_note')->nullable();
            $t->text('admin_note')->nullable();
            $t->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('inventory_released_at')->nullable();
            $t->timestamps();
            $t->index(['order_status', 'created_at']);
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('product_name');
            $t->string('sku');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('purchase_price');
            $t->unsignedBigInteger('selling_price');
            $t->unsignedBigInteger('shipping_cost')->default(0);
            $t->boolean('stock_reserved')->default(false);
            $t->string('supplier_url', 2048)->nullable();
            $t->timestamps();
        });
        Schema::create('supplier_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->string('supplier_order_number')->nullable();
            $t->unsignedBigInteger('cost');
            $t->unsignedBigInteger('shipping_cost')->default(0);
            $t->string('tracking_number')->nullable();
            $t->string('tracking_url', 2048)->nullable();
            $t->string('status', 30)->default('pending');
            $t->timestamp('ordered_at')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['products' => ['purchase_price', 'selling_price', 'shipping_cost', 'minimum_margin', 'stock'], 'product_variants' => ['purchase_price', 'selling_price', 'stock'], 'coupons' => ['amount', 'minimum_order', 'usage_count'], 'orders' => ['subtotal', 'shipping_total', 'discount_total', 'total'], 'order_items' => ['purchase_price', 'selling_price', 'shipping_cost'], 'supplier_orders' => ['cost', 'shipping_cost']] as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_nonnegative CHECK ({$column} >= 0)");
                }
            }
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
            DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_amount_type_valid CHECK ((type = 'percentage' AND amount <= 100) OR type = 'fixed')");
            DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_usage_valid CHECK (usage_limit IS NULL OR usage_count <= usage_limit)');
            DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_total_valid CHECK (total = subtotal + shipping_total - discount_total AND discount_total <= subtotal)');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_valid CHECK (role IN ('admin', 'customer'))");
        }
    }

    public function down(): void
    {
        foreach (['audit_logs', 'supplier_orders', 'order_items', 'orders', 'coupons', 'banners', 'product_variants', 'product_images', 'products', 'suppliers', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
