<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\WorkSessionResource;
use App\Models\WorkSession;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

class ListRunningSessions extends BaseWidget
{
    protected static ?int $sort = 100;
    protected int | string | array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('Work sessions') . ' - ' . __('Running');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WorkSession::query()
                    ->where('is_running', true)
            )
            ->columns(WorkSessionResource::table($table)->getColumns());
    }
}
