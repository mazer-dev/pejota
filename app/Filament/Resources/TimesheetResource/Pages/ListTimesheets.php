<?php

namespace App\Filament\Resources\TimesheetResource\Pages;

use App\Filament\Resources\TimesheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTimesheets extends ListRecords
{
    protected static string $resource = TimesheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * This function creates the Tabs at the top of the table.
     * It effectively acts as the "Approve Timesheets Page".
     */
    public function getTabs(): array
    {
        return [
            // Tab to view all entries
            'all' => Tab::make('All Entries'),
            
            // Tab for Pending items - This is your "Approve Page"
            'pending' => Tab::make('Pending Approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => \App\Models\Timesheet::where('status', 'pending')->count())
                ->badgeColor('warning'),
                
            // Tab to view already approved entries
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge(fn () => \App\Models\Timesheet::where('status', 'approved')->count())
                ->badgeColor('success'),
        ];
    }
}