<?php

namespace App\Livewire;

use App\Jobs\ProcessAssistantMessage;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Ai\AssistantQuickAnswers;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class AssistantChat extends Component
{
    public string $message = '';

    public ?int $conversationId = null;

    public function send(): void
    {
        $text = trim($this->message);

        if ($text === '') {
            return;
        }

        $conversation = $this->resolveConversation($text);

        $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $text,
        ]);

        $conversation->touch();

        ProcessAssistantMessage::dispatch($conversation, auth()->user());

        $this->message = '';

        $this->dispatch('assistant-chat-scroll');
    }

    public function pollTick(): void
    {
        $this->dispatch('assistant-chat-scroll');
    }

    public function runChip(string $chip): void
    {
        $chips = AssistantQuickAnswers::chips();

        if (! array_key_exists($chip, $chips)) {
            return;
        }

        $answer = app(AssistantQuickAnswers::class)->answer($chip);

        if ($answer === null) {
            return;
        }

        $label = $chips[$chip];
        $conversation = $this->resolveConversation($label);

        $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $label,
        ]);

        $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => $answer,
        ]);

        $conversation->touch();

        $this->dispatch('assistant-chat-scroll');
    }

    public function newConversation(): void
    {
        $this->conversationId = null;
    }

    public function continueConversation(int $conversationId): void
    {
        $this->conversationId = AssistantConversation::find($conversationId)?->id;
    }

    private function resolveConversation(string $firstMessage): AssistantConversation
    {
        if ($this->conversationId) {
            $existing = AssistantConversation::find($this->conversationId);

            if ($existing) {
                return $existing;
            }
        }

        $conversation = AssistantConversation::create([
            'company_id' => auth()->user()->company->id,
            'user_id' => auth()->id(),
            'title' => Str::limit($firstMessage, 60),
        ]);

        $this->conversationId = $conversation->id;

        return $conversation;
    }

    public function render(): View
    {
        $conversation = $this->conversationId
            ? AssistantConversation::with('messages')->find($this->conversationId)
            : null;

        if (! $conversation) {
            $this->conversationId = null;
        }

        $messages = $conversation?->messages ?? collect();

        return view('livewire.assistant-chat', [
            'messages' => $messages,
            'pending' => $messages->last()?->role === AssistantMessage::ROLE_USER,
            'chips' => AssistantQuickAnswers::chips(),
            'conversations' => AssistantConversation::query()
                ->latest('updated_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
