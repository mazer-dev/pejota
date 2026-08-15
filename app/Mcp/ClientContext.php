<?php

namespace App\Mcp;

use App\Models\Client;
use App\Models\ClientAiAnalysis;
use App\Models\ClientMcpToken;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single client an MCP connection is allowed to read.
 *
 * Every query used by the MCP tools starts here, always filtered by both the
 * company and the client that own the token. No tool ever receives a client id
 * as an argument, so there is no way for the agent to reach another client.
 */
class ClientContext
{
    public function __construct(
        public readonly Client $client,
        public readonly ClientMcpToken $token,
    ) {}

    public function clientId(): int
    {
        return (int) $this->client->id;
    }

    public function companyId(): int
    {
        return (int) $this->client->company_id;
    }

    public function projects(): Builder
    {
        return $this->scoped(Project::query());
    }

    public function tasks(): Builder
    {
        return $this->scoped(Task::query());
    }

    public function notes(): Builder
    {
        return $this->scoped(Note::query());
    }

    public function analyses(): Builder
    {
        return $this->scoped(ClientAiAnalysis::query());
    }

    public function conversations(): Builder
    {
        return $this->scoped(WhatsappConversation::query());
    }

    /**
     * Messages are scoped through the client's conversations: the client_id
     * column on messages is not always filled by the Evolution webhook.
     */
    public function messages(): Builder
    {
        return WhatsappMessage::query()
            ->where('company_id', $this->companyId())
            ->whereIn(
                'whatsapp_conversation_id',
                $this->conversations()->select('id')
            );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function scoped(Builder $query): Builder
    {
        return $query
            ->where('company_id', $this->companyId())
            ->where('client_id', $this->clientId());
    }
}
