<?php

namespace App\Filament\App\Resources\WhatsappConversationResource\RelationManagers;

use App\Enums\StatusPhaseEnum;
use App\Enums\WhatsappSuggestionStatusEnum;
use App\Enums\WhatsappSuggestionTypeEnum;
use App\Filament\App\Resources\NoteResource;
use App\Filament\App\Resources\TaskResource;
use App\Models\Note;
use App\Models\Status;
use App\Models\Task;
use App\Models\WhatsappAttachment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSuggestion;
use App\Services\Ai\CliWhatsappMessageSuggester;
use App\Services\Evolution\EvolutionApiClient;
use App\Services\Evolution\WhatsappAttachmentEnricher;
use App\Services\Evolution\WhatsappConversationSyncService;
use App\Services\Evolution\WhatsappConversationTokenService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

class MessagesRelationManager extends RelationManager
{
    use WithFileUploads;

    protected static string $relationship = 'messages';

    protected static ?string $title = 'Mensagens';

    public string $composerMessage = '';

    public ?string $aiSuggestion = null;

    public string $aiInstruction = '';

    public ?int $editingMessageId = null;

    public string $editingMessageText = '';

    /**
     * @var TemporaryUploadedFile|null
     */
    public $composerAttachment = null;

    /**
     * Session key used to hand a drafted message to this conversation's
     * composer (e.g. from the Task "Draft message" AI action). Stored with
     * session()->put() and consumed with session()->pull() on mount, so it
     * survives lazy-loading of the relation manager.
     */
    public static function draftSessionKey(WhatsappConversation|int $conversation): string
    {
        $id = $conversation instanceof WhatsappConversation ? $conversation->getKey() : $conversation;

        return "whatsapp_draft_{$id}";
    }

    public function mount(): void
    {
        parent::mount();

        $draft = session()->pull(self::draftSessionKey($this->getOwnerRecord()->getKey()));

        if (is_string($draft) && trim($draft) !== '') {
            $this->composerMessage = $draft;
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('text')
            ->defaultSort(fn ($query) => $query->reorder()->orderByDesc('sent_at')->orderByDesc('id'))
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->with('attachments'))
            ->content(fn (): View => view('filament.app.resources.whatsapp-conversation-resource.messages-chat'))
            ->headerActions([
                Action::make('syncMessages')
                    ->label('Sincronizar mensagens')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn () => $this->syncMessages()),
            ]);
    }

    /**
     * @return Collection<int, WhatsappSuggestion>
     */
    public function pendingSuggestions(): Collection
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        return WhatsappSuggestion::query()
            ->where('company_id', $conversation->company_id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->pending()
            ->orderBy('id')
            ->get();
    }

    public function acceptSuggestion(int $suggestionId): void
    {
        $suggestion = $this->findPendingSuggestion($suggestionId);

        if (! $suggestion) {
            Notification::make()
                ->title('Sugestão não encontrada ou já resolvida')
                ->warning()
                ->send();

            return;
        }

        try {
            if ($suggestion->type === WhatsappSuggestionTypeEnum::Task) {
                $task = $this->createTaskFromSuggestion($suggestion);

                $suggestion->forceFill([
                    'status' => WhatsappSuggestionStatusEnum::Accepted,
                    'accepted_at' => now(),
                    'task_id' => $task->id,
                ])->save();

                $title = 'Tarefa criada a partir da sugestão';
                $url = TaskResource::getUrl('edit', [$task]);
                $label = 'Ver tarefa';
            } else {
                $note = $this->createNoteFromSuggestion($suggestion);

                $suggestion->forceFill([
                    'status' => WhatsappSuggestionStatusEnum::Accepted,
                    'accepted_at' => now(),
                    'note_id' => $note->id,
                ])->save();

                $title = 'Anotação criada a partir da sugestão';
                $url = NoteResource::getUrl('view', [$note]);
                $label = 'Ver anotação';
            }
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Falha ao aceitar a sugestão')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($title)
            ->body($suggestion->title)
            ->success()
            ->actions([
                NotificationAction::make('view')
                    ->label($label)
                    ->url($url),
            ])
            ->send();
    }

    public function dismissSuggestion(int $suggestionId): void
    {
        $suggestion = $this->findPendingSuggestion($suggestionId);

        if (! $suggestion) {
            Notification::make()
                ->title('Sugestão não encontrada ou já resolvida')
                ->warning()
                ->send();

            return;
        }

        $suggestion->forceFill([
            'status' => WhatsappSuggestionStatusEnum::Dismissed,
            'dismissed_at' => now(),
        ])->save();

        Notification::make()
            ->title('Sugestão descartada')
            ->success()
            ->send();
    }

    private function findPendingSuggestion(int $suggestionId): ?WhatsappSuggestion
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        return WhatsappSuggestion::query()
            ->where('company_id', $conversation->company_id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->pending()
            ->find($suggestionId);
    }

    private function createTaskFromSuggestion(WhatsappSuggestion $suggestion): Task
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        $statusId = Status::query()
            ->where('phase', StatusPhaseEnum::TODO->value)
            ->orderBy('sort_order')
            ->value('id')
            ?? Status::query()->orderBy('sort_order')->value('id');

        if (! $statusId) {
            throw new RuntimeException('Cadastre ao menos um status de tarefa antes de aceitar sugestões.');
        }

        return Task::create([
            'title' => $suggestion->title,
            'description' => '<p>'.nl2br(e($suggestion->content)).'</p><p>'.e($this->suggestionOrigin($conversation)).'</p>',
            'client_id' => $conversation->client_id ?: $suggestion->client_id,
            'project_id' => $conversation->project_id ?: $suggestion->project_id,
            'status_id' => $statusId,
            'company_id' => $conversation->company_id,
        ]);
    }

    private function createNoteFromSuggestion(WhatsappSuggestion $suggestion): Note
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        return Note::create([
            'title' => $suggestion->title,
            'content' => [
                [
                    'type' => 'text',
                    'data' => [
                        'content' => $suggestion->content."\n\n".$this->suggestionOrigin($conversation),
                    ],
                ],
            ],
            'client_id' => $conversation->client_id ?: $suggestion->client_id,
            'project_id' => $conversation->project_id ?: $suggestion->project_id,
            'company_id' => $conversation->company_id,
        ]);
    }

    private function suggestionOrigin(WhatsappConversation $conversation): string
    {
        return 'Origem: conversa de WhatsApp com '.$conversation->display_name.' (#'.$conversation->id.').';
    }

    /**
     * Runs on wire:poll inside the web request, so it must stay light:
     * withMedia false skips media downloads and AI enrichment (full sync
     * with media stays on the manual "Sincronizar mensagens" action).
     */
    public function refreshMessages(): void
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        try {
            $newMessages = app(WhatsappConversationSyncService::class)
                ->sync($conversation, discoverCandidates: false, withMedia: false);

            if ($newMessages > 0) {
                $this->resetTable();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function startEditingMessage(int $messageId): void
    {
        $message = $this->findOwnMessage($messageId);

        if (! $message) {
            return;
        }

        if ($this->hasLocalOnlyRemoteId($message)) {
            Notification::make()
                ->title('Esta mensagem não pode ser editada')
                ->body('Ela não possui identificador do WhatsApp, então a edição não refletiria no aparelho do cliente.')
                ->warning()
                ->send();

            return;
        }

        if ($message->sent_at && $message->sent_at->diffInMinutes(now()) > 15) {
            Notification::make()
                ->title('Fora da janela de edição do WhatsApp')
                ->body('O WhatsApp só permite editar mensagens nos primeiros 15 minutos após o envio.')
                ->warning()
                ->send();

            return;
        }

        $this->editingMessageId = $message->id;
        $this->editingMessageText = (string) $message->text;
    }

    public function cancelEditingMessage(): void
    {
        $this->reset(['editingMessageId', 'editingMessageText']);
    }

    public function saveEditedMessage(): void
    {
        $this->validate([
            'editingMessageText' => ['required', 'string', 'max:12000'],
        ], attributes: ['editingMessageText' => 'mensagem']);

        $message = $this->editingMessageId ? $this->findOwnMessage($this->editingMessageId) : null;

        if (! $message) {
            $this->cancelEditingMessage();

            return;
        }

        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();
        $text = trim($this->editingMessageText);

        try {
            app(EvolutionApiClient::class)->updateMessage($conversation, $message, $text);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('O WhatsApp recusou a edição')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $message->forceFill(['text' => $text])->save();
        app(WhatsappConversationTokenService::class)->refresh($conversation);

        $this->cancelEditingMessage();
        $this->resetTable();

        Notification::make()
            ->title('Mensagem editada no WhatsApp')
            ->success()
            ->send();
    }

    public function deleteMessage(int $messageId): void
    {
        $message = $this->findOwnMessage($messageId);

        if (! $message) {
            return;
        }

        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        if (! $this->hasLocalOnlyRemoteId($message)) {
            try {
                app(EvolutionApiClient::class)->deleteMessageForEveryone($conversation, $message);
            } catch (Throwable $exception) {
                Notification::make()
                    ->title('O WhatsApp recusou a exclusão')
                    ->body('Provavelmente a janela de exclusão para todos já expirou. Detalhe: '.$exception->getMessage())
                    ->danger()
                    ->send();

                return;
            }
        }

        foreach ($message->attachments as $attachment) {
            if ($attachment->path && Storage::disk($attachment->disk ?: 'local')->exists($attachment->path)) {
                Storage::disk($attachment->disk ?: 'local')->delete($attachment->path);
            }

            $attachment->delete();
        }

        $message->delete();
        app(WhatsappConversationTokenService::class)->refresh($conversation);

        $this->resetTable();

        Notification::make()
            ->title('Mensagem excluída para todos no WhatsApp')
            ->success()
            ->send();
    }

    private function findOwnMessage(int $messageId): ?WhatsappMessage
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        $message = WhatsappMessage::query()
            ->where('company_id', $conversation->company_id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->where('from_me', true)
            ->find($messageId);

        if (! $message) {
            Notification::make()
                ->title('Mensagem não encontrada ou não é sua')
                ->body('Só é possível editar ou excluir mensagens enviadas por você.')
                ->warning()
                ->send();

            return null;
        }

        return $message;
    }

    /**
     * Messages saved without a WhatsApp id (Evolution response missing key.id)
     * get a "local-" uuid; they cannot be edited/revoked remotely.
     */
    private function hasLocalOnlyRemoteId(WhatsappMessage $message): bool
    {
        return blank($message->remote_message_id) || str_starts_with((string) $message->remote_message_id, 'local-');
    }

    public function generateAiSuggestion(): void
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        try {
            $suggestion = app(CliWhatsappMessageSuggester::class)->suggest($conversation, $this->composerMessage);

            $this->composerMessage = $suggestion;
            $this->aiSuggestion = $suggestion;

            Notification::make()
                ->title('Sugestão gerada')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Falha ao gerar sugestão de IA')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function generateAiFromInstruction(): void
    {
        $instruction = trim($this->aiInstruction);

        if ($instruction === '') {
            Notification::make()
                ->title('Descreva o que você quer comunicar ao cliente')
                ->warning()
                ->send();

            return;
        }

        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        try {
            $suggestion = app(CliWhatsappMessageSuggester::class)
                ->suggest($conversation, $this->composerMessage, $instruction);

            $this->composerMessage = $suggestion;
            $this->aiSuggestion = $suggestion;
            $this->aiInstruction = '';

            Notification::make()
                ->title('Mensagem gerada, revise antes de enviar')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Falha ao gerar mensagem com IA')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function removeComposerAttachment(): void
    {
        $this->reset('composerAttachment');
    }

    public function sendComposerMessage(): void
    {
        $this->validate([
            'composerMessage' => ['nullable', 'string', 'max:12000'],
            'composerAttachment' => ['nullable', 'file', 'max:25600'],
        ]);

        $text = trim($this->composerMessage);
        if ($text === '' && ! $this->composerAttachment instanceof TemporaryUploadedFile) {
            Notification::make()
                ->title('Escreva uma mensagem ou selecione um anexo')
                ->warning()
                ->send();

            return;
        }

        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();

        try {
            if ($this->composerAttachment instanceof TemporaryUploadedFile) {
                $this->sendMediaMessage($conversation, $this->composerAttachment, $text);
            } else {
                $this->sendTextMessage($conversation, $text);
            }

            $this->reset(['composerMessage', 'composerAttachment', 'aiSuggestion']);
            $this->resetTable();

            Notification::make()
                ->title('Mensagem enviada')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Falha ao enviar mensagem')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function syncMessages(): void
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();
        $count = app(WhatsappConversationSyncService::class)->sync($conversation);

        Notification::make()
            ->title('Mensagens sincronizadas')
            ->body(trans_choice('{0} Nenhuma mensagem foi importada.|{1} 1 mensagem foi importada.|[2,*] :count mensagens foram importadas.', $count, ['count' => $count]))
            ->success()
            ->send();
    }

    private function sendTextMessage(WhatsappConversation $conversation, string $text): WhatsappMessage
    {
        $response = app(EvolutionApiClient::class)->sendText($conversation, $text);

        $message = WhatsappMessage::create([
            'company_id' => $conversation->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'client_id' => $conversation->client_id,
            'project_id' => $conversation->project_id,
            'evolution_instance' => $conversation->evolution_instance,
            'remote_message_id' => $this->messageIdFromResponse($response),
            'remote_jid' => $conversation->remote_jid,
            'from_me' => true,
            'message_type' => 'text',
            'text' => $text,
            'status' => 'sent',
            'sent_at' => now(),
            'payload' => $response,
        ]);

        $this->afterOutgoingMessage($conversation);

        return $message;
    }

    private function sendMediaMessage(WhatsappConversation $conversation, TemporaryUploadedFile $file, string $caption): WhatsappMessage
    {
        $bytes = $file->get();
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $fileName = $file->getClientOriginalName() ?: 'anexo';
        $mediaType = $this->mediaTypeFromMime($mimeType);
        $response = app(EvolutionApiClient::class)->sendMedia(
            conversation: $conversation,
            base64: base64_encode($bytes),
            mimeType: $mimeType,
            fileName: $fileName,
            caption: $caption === '' ? null : $caption,
        );

        $message = WhatsappMessage::create([
            'company_id' => $conversation->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'client_id' => $conversation->client_id,
            'project_id' => $conversation->project_id,
            'evolution_instance' => $conversation->evolution_instance,
            'remote_message_id' => $this->messageIdFromResponse($response),
            'remote_jid' => $conversation->remote_jid,
            'from_me' => true,
            'message_type' => $mediaType,
            'text' => $caption === '' ? null : $caption,
            'status' => 'sent',
            'sent_at' => now(),
            'payload' => $response,
        ]);

        $this->storeOutgoingAttachment($message, $file, $bytes, $mimeType, $fileName, $mediaType, $response);
        $this->afterOutgoingMessage($conversation);

        return $message;
    }

    private function storeOutgoingAttachment(
        WhatsappMessage $message,
        TemporaryUploadedFile $file,
        string $bytes,
        string $mimeType,
        string $fileName,
        string $mediaType,
        array $response,
    ): void {
        $extension = $file->getClientOriginalExtension() ?: $this->extensionFromMime($mimeType) ?: 'bin';
        $path = 'whatsapp/'.$message->company_id.'/'.$message->id.'/'.Str::uuid().'.'.$extension;

        Storage::disk('local')->put($path, $bytes);

        $attachment = WhatsappAttachment::create([
            'company_id' => $message->company_id,
            'whatsapp_message_id' => $message->id,
            'disk' => 'local',
            'path' => $path,
            'original_filename' => $fileName,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size_bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'status' => 'stored',
            'payload' => [
                'direction' => 'outgoing',
                'type' => $mediaType,
                'response' => $response,
            ],
        ]);

        app(WhatsappAttachmentEnricher::class)->enrich($attachment, storage_path('app/'.$path), $message);
        $attachment->save();
    }

    private function afterOutgoingMessage(WhatsappConversation $conversation): void
    {
        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();

        app(WhatsappConversationTokenService::class)->refresh($conversation);
    }

    private function messageIdFromResponse(array $response): string
    {
        return data_get($response, 'key.id')
            ?: data_get($response, 'message.key.id')
            ?: data_get($response, 'data.key.id')
            ?: 'local-'.Str::uuid();
    }

    private function mediaTypeFromMime(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };
    }

    private function extensionFromMime(string $mimeType): ?string
    {
        $mimeType = str($mimeType)->before(';')->lower()->trim()->toString();

        return match ($mimeType) {
            'audio/ogg', 'audio/opus', 'application/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'audio/flac' => 'flac',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => null,
        };
    }
}
