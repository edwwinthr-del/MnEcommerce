<x-filament-panels::page>
    <div class="flex gap-4">
        <label>From <input type="date" wire:model.live="from" class="rounded border p-2"></label>
        <label>To <input type="date" wire:model.live="to" class="rounded border p-2"></label>
    </div>
    <p>Delivered, paid orders, filtered by order placement date. Estimated gross profit excludes tax, payment fees, returns, overhead and operating costs. It is not accounting net profit. Costs use immutable purchase-time snapshots.</p>
    @php($report = $this->report())
    <div class="grid grid-cols-2 gap-4">
        @foreach(['revenue'=>'Revenue (including customer delivery)','cogs'=>'Cost of goods','supplier_shipping'=>'Estimated supplier shipping','discounts'=>'Discounts','estimated_gross_profit'=>'Estimated gross profit','average_order_value'=>'Average order value'] as $key=>$label)
            <x-filament::section><span>{{ $label }}</span><div class="text-2xl">€{{ number_format($report[$key] / 100, 2) }}</div></x-filament::section>
        @endforeach
        <x-filament::section>Completed orders: {{ $report['orders'] }}</x-filament::section>
    </div>
    <x-filament::section heading="Best sellers">
        <table class="w-full text-left"><thead><tr><th>Product</th><th>Units</th><th>Sales before discount</th></tr></thead>
        <tbody>@forelse($report['top_products'] as $product)<tr><td>{{ $product->product_name }}</td><td>{{ $product->quantity }}</td><td>€{{ number_format($product->revenue / 100, 2) }}</td></tr>@empty<tr><td colspan="3">No completed orders in this period.</td></tr>@endforelse</tbody></table>
    </x-filament::section>
</x-filament-panels::page>
