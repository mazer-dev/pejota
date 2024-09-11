<?php

namespace App\Filament\App\Widgets;

use App\Enums\InvoiceStatusEnum;
use App\Helpers\PejotaHelper;
use App\Models\Invoice;
use App\Models\WorkSession;
use Carbon\CarbonImmutable;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvoicesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = CarbonImmutable::now(PejotaHelper::getUserTimeZone())->startOfDay();

        // TODO improve and centralize the money handling
        $total = Invoice::where('status', InvoiceStatusEnum::SENT->value)
                    ->sum('total') / 100;
        $fmt = \NumberFormatter::create(PejotaHelper::getUserLocate(), \NumberFormatter::CURRENCY);

        return [
            Stat::make(__('Invoices Opened'),
                $fmt->formatCurrency($total, PejotaHelper::getUserCurrency())
            )
                ->description(__('Invoices with status SENT'))
                ->icon('heroicon-o-currency-dollar')
                ->color(Color::hex('#dadada')),
        ];
    }
}
