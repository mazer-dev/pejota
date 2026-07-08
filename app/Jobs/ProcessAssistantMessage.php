<?php

namespace App\Jobs;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Ai\AssistantChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ProcessAssistantMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly AssistantConversation $conversation,
        public readonly User $user,
    ) {}

    public function handle(AssistantChatService $service): void
    {
        // Queue workers run without an authenticated user; log the
        // requesting user in for this job only (PejotaHelper and friends
        // read settings from auth()->user()).
        Auth::onceUsingId($this->user->id);

        try {
            $answer = $service->respond($this->conversation->fresh());
        } catch (Throwable $exception) {
            report($exception);

            $answer = __('The assistant failed to answer. Please try again.');
        }

        $this->conversation->messages()->create([
            'company_id' => $this->conversation->company_id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => $answer,
        ]);

        $this->conversation->touch();
    }
}
