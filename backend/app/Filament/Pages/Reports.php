<?php

namespace App\Filament\Pages;

use App\Services\AnalyticsService;
use Filament\Pages\Page;

class Reports extends Page
{
    protected static ?string $navigationLabel = 'Revenue & gross profit';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected string $view = 'filament.pages.reports';

    public ?string $from = null;

    public ?string $to = null;

    public function report(): array
    {
        $this->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->from ? ['after_or_equal:from'] : [])]]);

        return app(AnalyticsService::class)->report($this->from, $this->to);
    }
}
