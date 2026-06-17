<?php

namespace App\Services;

use App\Models\Task;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class DailyCheckService
{
    public static function query(): Builder
    {
        return Task::query()
            ->where('is_continuous', true)
            ->orderedForList();
    }

    public static function recordClasses(Task $task): ?string
    {
        if (! $task->is_continuous) {
            return null;
        }

        return $task->isDoneToday() ? 'fi-daily-check-done' : 'fi-daily-check-pending';
    }

    public static function toggle(Task $task): void
    {
        if (! $task->is_continuous) {
            return;
        }

        if ($task->isDoneToday()) {
            $task->markUndoneToday();

            return;
        }

        $task->markDoneToday();

        Notification::make()
            ->title(__('Done today').' — '.__('streak').': '.$task->currentStreak())
            ->success()
            ->send();
    }

    public static function doneTodayColumn(): IconColumn
    {
        return IconColumn::make('done_today')
            ->label(__('Today'))
            ->wrapHeader()
            ->getStateUsing(fn (Task $record): ?bool => $record->is_continuous ? $record->isDoneToday() : null)
            ->icon(fn (Task $record): ?string => ! $record->is_continuous
                ? null
                : ($record->isDoneToday() ? 'heroicon-s-check-circle' : 'heroicon-o-clock'))
            ->color(fn (Task $record): string => $record->isDoneToday() ? 'success' : 'warning')
            ->tooltip(fn (Task $record): ?string => ! $record->is_continuous
                ? null
                : ($record->isDoneToday() ? __('Checked in today').' — '.__('Click to undo') : __('Pending check-in today').' — '.__('Click to check in')))
            ->action(function (Task $record): void {
                self::toggle($record);
            });
    }

    public static function streakColumn(): TextColumn
    {
        return TextColumn::make('streak')
            ->label(__('Streak'))
            ->badge()
            ->icon('heroicon-o-fire')
            ->getStateUsing(fn (Task $record): ?int => $record->is_continuous ? $record->currentStreak() : null)
            ->color(fn (Task $record): string => $record->is_continuous && $record->currentStreak() > 0 ? 'success' : 'gray')
            ->tooltip(fn (Task $record): ?string => $record->is_continuous ? __('Consecutive days checked in').($record->isDoneToday() ? '' : ' — '.__('not done today')) : null);
    }
}
