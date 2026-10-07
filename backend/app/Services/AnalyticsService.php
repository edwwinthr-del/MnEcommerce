<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function report(?string $from = null, ?string $to = null): array
    {
        $orders = Order::query()->where('order_status', 'delivered')->where('payment_status', 'paid')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));
        $aggregate = (clone $orders)->toBase()->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as revenue, COALESCE(SUM(discount_total),0) as discounts')->first();
        $ids = (clone $orders)->select('id');
        $costs = DB::table('order_items')->whereIn('order_id', $ids)->selectRaw('COALESCE(SUM(purchase_price * quantity),0) as cogs, COALESCE(SUM(shipping_cost * quantity),0) as supplier_shipping')->first();
        $count = (int) $aggregate->count;
        $revenue = (int) $aggregate->revenue;

        return ['revenue' => $revenue, 'cogs' => (int) $costs->cogs, 'supplier_shipping' => (int) $costs->supplier_shipping, 'discounts' => (int) $aggregate->discounts, 'estimated_gross_profit' => $revenue - (int) $costs->cogs - (int) $costs->supplier_shipping, 'average_order_value' => $count ? intdiv($revenue, $count) : 0, 'orders' => $count,
            'top_products' => DB::table('order_items')->whereIn('order_id', $ids)->selectRaw('product_id, product_name, SUM(quantity) as quantity, SUM(quantity * selling_price) as revenue')->groupBy('product_id', 'product_name')->orderByDesc('quantity')->limit(10)->get()->toArray()];
    }
}
