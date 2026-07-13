<?php

namespace App\Services\Ai\Context;

use App\Models\Client;
use App\Models\Project;
use App\Models\WhatsappConversation;
use App\Services\Ai\ConversationContextBuilder;

/**
 * Orchestrates every context section into the two context profiles used
 * across the AI features:
 *
 * - forSuggestion(): built around one active WhatsApp conversation, used to
 *   draft the next message to send. It includes the full stored history and
 *   the latest saved ClientAiAnalysis (if any) as
 *   background, on top of the current facts (tasks, invoices, etc).
 * - forAnalysis(): built around the whole client relationship (every
 *   WhatsApp conversation the client has). History is NOT capped. The
 *   previous analysis is intentionally left out here (rather than being
 *   included as "análise anterior") so the new analysis is judged purely
 *   on current facts, not anchored to a previous read of the relationship.
 */
class ClientContextBuilder
{
    public function __construct(
        private readonly ConversationContextBuilder $identityBuilder,
        private readonly ConversationHistoryRenderer $historyRenderer,
        private readonly TasksContextSection $tasksSection,
        private readonly InvoicesContextSection $invoicesSection,
        private readonly ContractsContextSection $contractsSection,
        private readonly NotesContextSection $notesSection,
        private readonly WorkSessionsContextSection $workSessionsSection,
        private readonly LuizStyleContextSection $luizStyleSection,
        private readonly ClientAnalysisContextSection $clientAnalysisSection,
    ) {}

    public function forSuggestion(WhatsappConversation $conversation): string
    {
        $conversation->loadMissing(['client', 'project', 'messages.attachments']);

        $client = $conversation->client;
        $project = $conversation->project;

        $history = $this->historyRenderer->render($conversation->messages, 'Luiz');

        return $this->assemble($client, $project, $conversation, $history, includeAnalysis: true);
    }

    public function forAnalysis(Client $client): string
    {
        $client->loadMissing(['whatsappConversations.messages.attachments']);

        $messages = $client->whatsappConversations->flatMap(fn (WhatsappConversation $conversation) => $conversation->messages);

        $history = $this->historyRenderer->render($messages, 'Luiz', null);

        return $this->assemble($client, null, null, $history, includeAnalysis: false);
    }

    /**
     * Facts + saved analysis + writing-style sample for a client (and,
     * optionally, a project), without any specific WhatsApp conversation.
     * Used by features that need the client's context but aren't drafting
     * a WhatsApp reply (e.g. task summaries and subtask suggestions).
     */
    public function forClientFacts(?Client $client, ?Project $project = null): string
    {
        if (! $client && ! $project) {
            return '';
        }

        return $this->assemble($client, $project, null, '', includeAnalysis: true);
    }

    private function assemble(?Client $client, ?Project $project, ?WhatsappConversation $conversation, string $history, bool $includeAnalysis): string
    {
        $sections = [
            $this->identityBuilder->build($client, $project, $history !== '' ? $history : null, $conversation),
            $this->tasksSection->build($client, $project),
            $this->invoicesSection->build($client, $project),
            $this->contractsSection->build($client),
            $this->notesSection->build($client, $project),
            $this->workSessionsSection->build($client, $project),
            $this->luizStyleSection->build($client, $conversation),
        ];

        if ($includeAnalysis) {
            $sections[] = $this->clientAnalysisSection->build($client);
        }

        return collect($sections)->filter()->implode("\n\n");
    }
}
