<?php

namespace App\Providers;

use App\Models\Task;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Parallax\FilamentComments\Models\FilamentComment;

class EventServiceProvider extends ServiceProvider
{

    public function boot()
    {
        parent::boot();

        Event::listen(
            FilamentComment::creating(function ($comment) {
                if ($comment->subject_type == "App\\Models\\Task") {
                    $task = Task::where('id', $comment->subject_id)->first();
                    
                    $properties = [
                        'comment' => 'New comment: '. strip_tags($comment->comment),
                        'status.name'=> $task->status->name, // Supondo que Task tenha uma relação com Status
                    ];
        
                    $dirtyAttributes = $task->getDirty();
                    $dirtyProperties = [];

                    foreach ($dirtyAttributes as $key => $value) {
                        $dirtyProperties[$key] = [
                            'old' => $task->getOriginal($key),
                            'new' => $value,
                        ];
                    }

                    activity('task')
                        ->performedOn($task)
                        ->withProperties(['attributes' => $properties])
                        ->log('update');
                }
            })
        );
    }
}
