<?php

namespace App\Filament\App\Resources\TaskResource\Pages;

use App\Filament\App\Resources\TaskResource;
use App\Models\Task;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Parallax\FilamentComments\Actions\CommentsAction;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CommentsAction::make(),
            Actions\DeleteAction::make()->before(function (Actions\DeleteAction $action, Task $record) {
                if ($record->workSessions->count() > 0) {
                    Notification::make()
                        ->danger()
                        ->title(__('Cannot delete task'))
                        ->body(
                            __('This task cannot be deleted because it has work sessions associated with it.')
                        )
                        ->send();

                    $action->halt();
                }
            }),
        ];
    }

    protected function getFormActions(): array
    {
        $actions = parent::getFormActions();

        $actions[] = Actions\Action::make('list')
            ->translateLabel()
            ->url(ListTasks::getUrl())
            ->icon('heroicon-o-chevron-left')
            ->color(Color::Neutral);

        return $actions;
    }
}
