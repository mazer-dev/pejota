<?php

namespace App\Livewire;

use App\Filament\App\Resources\TaskResource;
use App\Filament\App\Resources\WorkSessionResource;
use App\Filament\App\Resources\WorkSessionResource\Pages\CreateWorkSession;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Support\Enums\ActionSize;
use Livewire\Component;

class TopNavigateAction extends Component implements HasActions
{
    use InteractsWithActions;
    public function render()
    {
        return view('livewire.top-navigate-action');
    }

    public function getAction(array|string $name): ?Action
    {
        return Action::make($name);
    }

    public function createTask(array|string $name = 'Create Task'): ?Action
    {
        return Action::make($name)
            ->hiddenLabel()
            ->size(ActionSize::Small)
            ->tooltip('Create a new Task')
            ->label('Create Task')
            ->icon(TaskResource::getNavigationIcon())
            ->url(
                TaskResource\Pages\CreateTask::getUrl()
            );
    }
    public function createSession(array|string $name = 'Create Session'): ?Action
    {
        return Action::make($name)
            ->hiddenLabel()
            ->size(ActionSize::Small)
            ->tooltip('Create a new Work Session')
            ->label('Create Session')
            ->icon(WorkSessionResource::getNavigationIcon())
            ->color(Color::Amber)
            ->url(
                CreateWorkSession::getUrl()
            );
    }

    public function getActiveActionsLocale(): ?string
    {
        // TODO: Implement getActiveActionsLocale() method.
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        // TODO: Implement makeFilamentTranslatableContentDriver() method.
    }
}
