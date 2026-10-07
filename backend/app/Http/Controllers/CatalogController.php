<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'string', 'max:120'], 'sort' => ['nullable', Rule::in(['featured', 'price_asc', 'price_desc', 'newest', 'bestselling'])], 'featured' => ['nullable', 'boolean'], 'new' => ['nullable', 'boolean'], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $query = Product::query()->published()->with(['category', 'images', 'variants'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('short_description', 'like', '%'.addcslashes($search, '%_\\').'%');
            }))
            ->when($filters['category'] ?? null, fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->boolean('featured'), fn ($q) => $q->where('is_featured', true))->when($request->boolean('new'), fn ($q) => $q->where('is_new', true));
        match ($filters['sort'] ?? 'featured') {
            'price_asc' => $query->orderBy('selling_price'),'price_desc' => $query->orderByDesc('selling_price'),'newest' => $query->orderByDesc('created_at'),
            'bestselling' => $query->orderByDesc(DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->selectRaw('COALESCE(SUM(quantity),0)')->whereColumn('order_items.product_id', 'products.id')->where('orders.order_status', 'delivered')),
            default => $query->orderByDesc('is_featured'),
        };

        return ProductResource::collection($query->orderBy('id')->paginate(12)->withQueryString());
    }

    public function show(string $slug): ProductResource
    {
        return new ProductResource(Product::query()->published()->with(['category', 'images', 'variants'])->where('slug', $slug)->firstOrFail());
    }

    public function categories()
    {
        return response()->json(['data' => Category::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug', 'description', 'image'])]);
    }

    public function banners()
    {
        return response()->json(['data' => Banner::query()->where('is_active', true)->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))->orderBy('sort_order')->get(['id', 'title', 'subtitle', 'image', 'mobile_image', 'button_text', 'button_url', 'position'])]);
    }
}
