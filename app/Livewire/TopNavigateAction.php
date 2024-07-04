<?php

namespace App\Livewire;

use App\Filament\App\Resources\NoteResource;
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
                TaskResource\Pages\CreateTask::getUrl(panel: 'create')
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
                CreateWorkSession::getUrl(panel: 'create')
            );
    }

    public function createNote(array|string $name = 'Create Note'): ?Action
    {
        return Action::make($name)
            ->hiddenLabel()
            ->size(ActionSize::Small)
            ->tooltip('Create a new Note')
            ->label('Create Note')
            ->icon(NoteResource::getNavigationIcon())
            ->color(Color::Cyan)
            ->url(
                NoteResource\Pages\CreateNote::getUrl(panel: 'create')
            );
    }

    public function getActiveActionsLocale(): ?string
    {
        return Action::getActiveActionsLocale();
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return new TranslatableContentDriver(app()->getLocale());
    }
}
