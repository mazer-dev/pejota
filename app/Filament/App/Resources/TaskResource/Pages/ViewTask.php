<?php

namespace App\Filament\App\Resources\TaskResource\Pages;

use App\Filament\App\Resources\TaskResource;
use App\Models\Status;
use App\Models\WorkSession;
use Filament\Facades\Filament;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\On;

class ViewTask extends ViewRecord {
    protected static string $resource = TaskResource::class;

    public function getTitle(): string|Htmlable {
        return $this->record->title;
    }

    public function handleTaskClose($data, $taskId) 
    {
        $status = Status::findOrFail($data["status_id"]);
        $userId = Filament::auth()->id();

        if ($status->phase == "closed") {
            $runningWorkSession = WorkSession::where(['is_running' => 1, "task_id" => $taskId])
                ->where('user_id', $userId)
                ->first();

            if ($runningWorkSession) {
                return (Notification::make()
                    ->title('Saved successfully')
                    ->success()
                    ->body('An active work session is in progress. Would you like to close it?')
                    ->duration(10000)
                    ->actions([
                        Action::make('Close session')
                            ->button()
                            ->dispatch('handle-work-session-close', [$runningWorkSession->id])
                            ->close(),
                        Action::make('Keep session open')
                            ->color('gray')
                            ->close(),
                    ])
                    ->send());
            }
        }
    }

    #[On("handle-work-session-close")]
    public function handleWorkSessionClose(int $workSessionId) {
        WorkSession::find($workSessionId)
            ?->update(['is_running' => 0]);
    }
}
