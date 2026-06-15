<?php

namespace App\Filament\App\Widgets;

use App\Helpers\PejotaHelper;
use App\Models\Invoice;
use App\Services\InvoiceService;
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
        $service = new InvoiceService;

        $pending = $service->sumBaseCurrency(Invoice::pending()->get());
        $overdue = $service->sumBaseCurrency(Invoice::overdue()->get());
        $dueSoon = $service->sumBaseCurrency(Invoice::dueWithin(30)->get());
        $received = $service->sumBaseCurrency(Invoice::receivedBetween($today->subDays(30), $today)->get());

        $fmt = NumberFormatter::create(PejotaHelper::getUserLocate(), NumberFormatter::CURRENCY);
        $currency = PejotaHelper::getUserCurrency();

        return [
            Stat::make(__('Total pending'), $fmt->formatCurrency($pending['total'], $currency))
                ->description($this->describe(__('Pending invoices (sent + partially paid)'), $pending['unconverted']))
                ->icon('heroicon-o-currency-dollar')
                ->color(Color::hex('#dadada')),

            Stat::make(__('Pending overdue'), $fmt->formatCurrency($overdue['total'], $currency))
                ->description($this->describe(__('Pending and overdue'), $overdue['unconverted']))
                ->icon('heroicon-o-exclamation-circle')
                ->color(Color::hex('#fadada')),

            Stat::make(__('Due within 30 days'), $fmt->formatCurrency($dueSoon['total'], $currency))
                ->description($this->describe(__('Pending due in the next 30 days'), $dueSoon['unconverted']))
                ->icon('heroicon-o-clock')
                ->color(Color::hex('#fdf6da')),

            Stat::make(__('Received last 30 days'), $fmt->formatCurrency($received['total'], $currency))
                ->description($this->describe(__('Paid in the last 30 days'), $received['unconverted']))
                ->icon('heroicon-o-check')
                ->color(Color::hex('#daFada')),
        ];
    }

    private function describe(string $base, int $unconverted): string
    {
        return $unconverted > 0
            ? $base.' · '.__(':count not converted', ['count' => $unconverted])
            : $base;
    }
}
