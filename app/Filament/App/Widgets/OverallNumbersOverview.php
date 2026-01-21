<?php

namespace App\Filament\App\Widgets;

use App\Enums\InvoiceStatusEnum;
use App\Enums\StatusPhaseEnum;
use App\Filament\App\Resources\ClientResource;
use App\Filament\App\Resources\ProjectResource;
use App\Filament\App\Resources\TaskResource;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverallNumbersOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(__('Clients'), Client::count())
                ->icon(ClientResource::getNavigationIcon()),

            Stat::make(__('Projects'), Project::count())
                ->icon(ProjectResource::getNavigationIcon()),

            // app/Filament/App/Widgets/OverallNumbersOverview.php

            Stat::make('Invoices opened', '$' . number_format(Invoice::where('status', InvoiceStatusEnum::SENT)->sum('total'), 2)) // Changed total_amount to total
                ->description('Invoices with status SENT')
                ->color('info'),

            Stat::make('Received this month', '$' . number_format(Invoice::where('status', InvoiceStatusEnum::PAID)->whereMonth('updated_at', now()->month)->sum('total'), 2)) // Changed total_amount to total
                ->description('Invoices PAID this month')
                ->color('success'),

            Stat::make(__('Tasks Opened'), Task::count())
                ->description('Ongoing project activities')
                ->icon(TaskResource::getNavigationIcon())
                ->color('primary'),

            // If you want a specific "Completion" stat on your dashboard:
            Stat::make(__('Average Progress'), Task::avg('percent_complete') . '%')
                ->description('Completion rate across all projects')
                ->icon('heroicon-m-chart-pie')
                ->color('success'),
        ];
    }
}
