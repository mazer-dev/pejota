<?php

namespace App\Filament\App\Widgets;

use App\Filament\App\Resources\TaskResource\Pages\ViewTask;
use App\Models\Task;
use App\Services\DailyCheckService;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

class ListDailyChecks extends BaseWidget
{
    protected static ?int $sort = 101;

    protected int|string|array $columnSpan = ['default' => 'full', 'md' => 3];

    public static function canView(): bool
    {
        return DailyCheckService::query()->exists();
    }

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('Daily checks');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => DailyCheckService::query())
            ->recordClasses(fn (Task $record): ?string => DailyCheckService::recordClasses($record))
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->wrap()
                    ->weight(FontWeight::Bold),
                TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->toggleable(),
                DailyCheckService::streakColumn(),
                DailyCheckService::doneTodayColumn(),
            ])
            ->recordUrl(fn (Task $record): string => ViewTask::getUrl([$record->id]));
    }
}
