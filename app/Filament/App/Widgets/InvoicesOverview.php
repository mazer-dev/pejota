<?php

namespace App\Filament\App\Widgets;

use App\Helpers\PejotaHelper;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Filament\Support\Colors\Color;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use NumberFormatter;

class InvoicesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $today = CarbonImmutable::now(PejotaHelper::getUserTimeZone())->startOfDay();

        $totalPending = Invoice::pending()->sum('total') / 100;
        $totalOverdue = Invoice::overdue()->sum('total') / 100;
        $totalDueSoon = Invoice::dueWithin(30)->sum('total') / 100;
        $totalReceived = Invoice::receivedBetween($today->subDays(30), $today)->sum('total') / 100;

        $fmt = NumberFormatter::create(PejotaHelper::getUserLocate(), NumberFormatter::CURRENCY);
        $currency = PejotaHelper::getUserCurrency();

        return [
            Stat::make(__('Total pending'), $fmt->formatCurrency($totalPending, $currency))
                ->description(__('Pending invoices (sent + partially paid)'))
                ->icon('heroicon-o-currency-dollar')
                ->color(Color::hex('#dadada')),

            Stat::make(__('Pending overdue'), $fmt->formatCurrency($totalOverdue, $currency))
                ->description(__('Pending and overdue'))
                ->icon('heroicon-o-exclamation-circle')
                ->color(Color::hex('#fadada')),

            Stat::make(__('Due within 30 days'), $fmt->formatCurrency($totalDueSoon, $currency))
                ->description(__('Pending due in the next 30 days'))
                ->icon('heroicon-o-clock')
                ->color(Color::hex('#fdf6da')),

            Stat::make(__('Received last 30 days'), $fmt->formatCurrency($totalReceived, $currency))
                ->description(__('Paid in the last 30 days'))
                ->icon('heroicon-o-check')
                ->color(Color::hex('#daFada')),
        ];
    }
}
