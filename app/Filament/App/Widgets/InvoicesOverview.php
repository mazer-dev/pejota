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
        $totalOpened = Invoice::where('status', InvoiceStatusEnum::SENT->value)
                    ->sum('total') / 100;

        $totalOverdue = Invoice::where('status', InvoiceStatusEnum::SENT->value)
                    ->where('due_date', '<', $today)
                    ->sum('total') / 100;

        $totalPaid = Invoice::where('status', InvoiceStatusEnum::PAID->value)
                    ->where('payment_date', '>=', $today->startOfMonth())
                    ->sum('total') / 100;
        $fmt = \NumberFormatter::create(PejotaHelper::getUserLocate(), \NumberFormatter::CURRENCY);

        return [
            Stat::make(__('Invoices opened'),
                $fmt->formatCurrency($totalOpened, PejotaHelper::getUserCurrency())
            )
                ->description(__('Invoices with status SENT'))
                ->icon('heroicon-o-currency-dollar')
                ->color(Color::hex('#dadada')),

            Stat::make(__('Invoices overdue'),
                $fmt->formatCurrency($totalOverdue, PejotaHelper::getUserCurrency())
            )
                ->description(__('Invoices SENT and overdue'))
                ->icon('heroicon-o-currency-dollar')
                ->color(Color::hex('#fadada')),

            Stat::make(__('Received this month'),
                $fmt->formatCurrency($totalPaid, PejotaHelper::getUserCurrency())
            )
                ->description(__('Invoices PAID this month'))
                ->icon('heroicon-o-currency-dollar')
                ->color(Color::hex('#daFada')),
        ];
    }
}
