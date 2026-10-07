<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Mora curated supply'], ['website' => 'https://example.com', 'is_active' => true]);
        $categories = [];
        foreach (['audio' => 'Audio', 'home-living' => 'Home & living', 'everyday-tech' => 'Everyday tech', 'accessories' => 'Accessories'] as $slug => $name) {
            $categories[$slug] = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => 'Considered essentials for your everyday.', 'sort_order' => count($categories)]);
        }
        $products = [
            ['studio-wireless-headphones', 'Studio wireless headphones', 'audio', 8900, 11900, 4200, 'photo-1546435770-a3e426bf472b', 'A little quiet. A lot of detail. Comfortable wireless sound for wherever the day goes.'],
            ['arc-desk-lamp', 'Arc desk lamp', 'home-living', 4900, null, 2100, 'photo-1507473885765-e6ed057f782c', 'A warm pool of light, right where you need it. A sculptural essential for your desk.'],
            ['everyday-canvas-tote', 'Everyday canvas tote', 'accessories', 2400, null, 900, 'photo-1553062407-98eeb64c6a62', 'Room for the everyday, made with durable cotton canvas and a clean silhouette.'],
            ['mini-bluetooth-speaker', 'Mini Bluetooth speaker', 'audio', 3900, 4900, 1700, 'photo-1608043152269-423dbba4e7e1', 'Good sound, small footprint. Bring your favourite playlists along.'],
            ['slim-wireless-charger', 'Slim wireless charger', 'everyday-tech', 2900, null, 1100, 'photo-1586953208448-b95a79798f07', 'A simple landing spot for your phone. Compatible with Qi wireless charging.'],
            ['ceramic-coffee-set', 'Ceramic coffee set', 'home-living', 3400, null, 1400, 'photo-1514228742587-6b1558fcca3d', 'Two thoughtfully shaped ceramic cups for a slower morning ritual.'],
            ['travel-organizer', 'Travel organizer', 'accessories', 2200, null, 800, 'photo-1553062407-98eeb64c6a62', 'Keep cables and small essentials together, at home and away.'],
            ['insulated-water-bottle', 'Insulated water bottle', 'accessories', 2700, 3200, 1000, 'photo-1602143407151-7111542de6e8', 'A reusable stainless steel companion for the commute, the trail and everything between.'],
        ];
        foreach ($products as $index => $row) {
            [$slug,$name,$category,$price,$old,$cost,$photo,$description] = $row;
            $product = Product::firstOrCreate(['slug' => $slug], ['name' => $name, 'category_id' => $categories[$category]->id, 'supplier_id' => $supplier->id, 'sku' => 'MORA-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT), 'brand' => 'Mora Edit', 'short_description' => $description, 'description' => $description."\n\nSelected for useful design and everyday comfort. Product images are illustrative; refer to the listed specifications before ordering.", 'purchase_price' => $cost, 'selling_price' => $price, 'old_price' => $old, 'shipping_cost' => 150, 'minimum_margin' => 300, 'stock' => 25, 'status' => 'active', 'is_featured' => $index < 4, 'is_new' => $index >= 4, 'estimated_delivery_min' => 3, 'estimated_delivery_max' => 7, 'specifications' => ['Collection' => 'Everyday essentials', 'Warranty' => 'Contact support for warranty details'], 'supplier_url' => 'https://example.com/products/'.$slug]);
            $product->images()->firstOrCreate(['image_url' => 'https://images.unsplash.com/'.$photo.'?auto=format&fit=crop&w=1200&q=85'], ['is_primary' => true, 'sort_order' => 0]);
            if ($index === 0) {
                foreach (['Graphite', 'Sand'] as $variant) {
                    $product->variants()->firstOrCreate(['sku' => $product->sku.'-'.strtoupper($variant)], ['name' => $variant, 'purchase_price' => $cost, 'selling_price' => $price, 'stock' => 12]);
                }
            }
        }
        Banner::firstOrCreate(['title' => 'Good things. Everyday.'], ['subtitle' => 'Thoughtfully chosen essentials for your space, your routine, your life.', 'image' => 'https://images.unsplash.com/photo-1490312278390-ab64016e0aa9?auto=format&fit=crop&w=1800&q=90', 'button_text' => 'Explore the collection', 'button_url' => '/shop', 'position' => 'hero']);
        Coupon::firstOrCreate(['code' => 'DOBRODOSLI10'], ['type' => 'percentage', 'amount' => 10, 'minimum_order' => 2000, 'usage_limit' => 100, 'active' => true]);
    }
}
