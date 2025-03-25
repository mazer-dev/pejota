<?php

namespace App\Livewire;

use App\Models\WorkSession;
use Livewire\Component;

class WorkSessionsTopNav extends Component
{
    public function render()
    {
        return view('livewire.work-sessions-top-nav', [
            'count' =>WorkSession::where('is_running', true)
                ->count(),
        ]);
    }
}
