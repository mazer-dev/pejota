<?php

namespace App\Mcp\Tools;

use App\Models\WhatsappConversation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Conversas de WhatsApp do cliente')]
#[Description('Lista as conversas de WhatsApp vinculadas ao cliente desta conexão. Use o id retornado aqui em list_whatsapp_messages.')]
class ListWhatsappConversationsTool extends ClientScopedTool
{
    protected string $name = 'list_whatsapp_conversations';

    public function handle(Request $request): Response
    {
        $conversations = $this->context()->conversations()
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->limit($this->boundedLimit($request->get('limit'), 25, 100))
            ->get();

        return $this->json([
            'total' => $conversations->count(),
            'conversations' => $conversations->map(fn (WhatsappConversation $conversation): array => [
                'id' => $conversation->id,
                'name' => $conversation->name,
                'phone_number' => $conversation->phone_number,
                'is_group' => (bool) $conversation->is_group,
                'status' => $conversation->status,
                'messages' => $conversation->messages_count,
                'last_message_at' => $this->dateTime($conversation->last_message_at),
                'notes' => $this->excerpt($conversation->notes, 500),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()
                ->description('Quantidade máxima de conversas (padrão 25, máximo 100).'),
        ];
    }
}
