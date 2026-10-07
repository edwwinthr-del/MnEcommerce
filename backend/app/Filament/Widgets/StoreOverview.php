<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Services\AnalyticsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $r = app(AnalyticsService::class)->report();

        return [
            Stat::make('Completed revenue', '€'.number_format($r['revenue'] / 100, 2))->description('Delivered and paid'),
            Stat::make('Estimated gross profit', '€'.number_format($r['estimated_gross_profit'] / 100, 2))->description('Before tax, fees and overhead'),
            Stat::make('Completed orders', $r['orders']),
            Stat::make('Products needing margin review', Product::where('margin_flagged', true)->count())->color('warning'),
        ];
    }
}
