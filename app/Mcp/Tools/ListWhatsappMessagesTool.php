<?php

namespace App\Mcp\Tools;

use App\Models\WhatsappAttachment;
use App\Models\WhatsappMessage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Mensagens de WhatsApp do cliente')]
#[Description('Lê as mensagens de WhatsApp do cliente desta conexão, em ordem cronológica. Sem conversation_id, junta todas as conversas do cliente. Use before_message_id para continuar subindo no histórico.')]
class ListWhatsappMessagesTool extends ClientScopedTool
{
    protected string $name = 'list_whatsapp_messages';

    public function handle(Request $request): Response
    {
        $query = $this->context()->messages()
            ->with(['attachments', 'conversation']);

        $conversationId = $request->get('conversation_id');
        if (is_numeric($conversationId)) {
            $belongsToClient = $this->context()->conversations()
                ->whereKey((int) $conversationId)
                ->exists();

            if (! $belongsToClient) {
                return Response::error('Conversa não encontrada para este cliente.');
            }

            $query->where('whatsapp_conversation_id', (int) $conversationId);
        }

        $search = $request->get('search');
        if (is_string($search) && trim($search) !== '') {
            $query->where('text', 'like', '%'.trim($search).'%');
        }

        $beforeId = $request->get('before_message_id');
        if (is_numeric($beforeId)) {
            $query->where('id', '<', (int) $beforeId);
        }

        $messages = $query
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit($this->boundedLimit($request->get('limit'), 50, 200))
            ->get()
            ->sortBy([['sent_at', 'asc'], ['id', 'asc']])
            ->values();

        return $this->json([
            'total' => $messages->count(),
            'oldest_message_id' => $messages->first()?->id,
            'messages' => $messages->map(fn (WhatsappMessage $message): array => [
                'id' => $message->id,
                'conversation' => $message->conversation?->name,
                'sent_at' => $this->dateTime($message->sent_at),
                'from_me' => (bool) $message->from_me,
                'sender' => $message->from_me ? 'Luiz' : ($message->sender_name ?: 'Cliente'),
                'type' => $message->message_type,
                'text' => $message->text,
                'attachments' => $message->attachments->map(fn (WhatsappAttachment $attachment): array => [
                    'filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'transcription' => $this->excerpt($attachment->transcription_text, 2000),
                    'extracted_text' => $this->excerpt($attachment->extracted_text, 2000),
                ])->all(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation_id' => $schema->integer()
                ->description('Id da conversa, obtido em list_whatsapp_conversations.'),
            'search' => $schema->string()
                ->description('Texto procurado no corpo das mensagens.'),
            'before_message_id' => $schema->integer()
                ->description('Retorna apenas mensagens anteriores a este id, para paginar histórico antigo.'),
            'limit' => $schema->integer()
                ->description('Quantidade máxima de mensagens (padrão 50, máximo 200).'),
        ];
    }
}
