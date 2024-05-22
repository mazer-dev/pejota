<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Services\CompanyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateCompanyForUser
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserCreated $event): void
    {
        (new CompanyService())->create($event->user);
    }
}
