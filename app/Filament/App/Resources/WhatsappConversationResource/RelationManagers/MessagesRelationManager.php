<?php

namespace App\Filament\App\Resources\WhatsappConversationResource\RelationManagers;

use App\Helpers\PejotaHelper;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Evolution\EvolutionApiClient;
use App\Services\Evolution\WhatsappConversationSyncService;
use App\Services\Evolution\WhatsappConversationTokenService;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('text')
            ->defaultSort('sent_at', 'desc')
            ->columns([
                TextColumn::make('sent_at')
                    ->label(__('Time'))
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable(),
                TextColumn::make('direction')
                    ->label(__('Direction'))
                    ->badge(),
                TextColumn::make('message_type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('text')
                    ->label(__('Message'))
                    ->wrap()
                    ->limit(180)
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->placeholder('-'),
            ])
            ->headerActions([
                Action::make('syncMessages')
                    ->label('Sincronizar mensagens')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn () => $this->syncMessages()),
                Action::make('sendMessage')
                    ->label(__('Send message'))
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Textarea::make('message')
                            ->label(__('Message'))
                            ->required()
                            ->rows(5),
                    ])
                    ->action(fn (array $data) => $this->sendMessage((string) $data['message'])),
            ]);
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

    private function sendMessage(string $text): void
    {
        /** @var WhatsappConversation $conversation */
        $conversation = $this->getOwnerRecord();
        $response = app(EvolutionApiClient::class)->sendText($conversation, $text);
        $messageId = data_get($response, 'key.id')
            ?: data_get($response, 'message.key.id')
            ?: data_get($response, 'data.key.id')
            ?: 'local-'.Str::uuid();

        WhatsappMessage::create([
            'company_id' => $conversation->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'client_id' => $conversation->client_id,
            'project_id' => $conversation->project_id,
            'evolution_instance' => $conversation->evolution_instance,
            'remote_message_id' => $messageId,
            'remote_jid' => $conversation->remote_jid,
            'from_me' => true,
            'message_type' => 'text',
            'text' => $text,
            'status' => 'sent',
            'sent_at' => now(),
            'payload' => $response,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();

        app(WhatsappConversationTokenService::class)->refresh($conversation);

        Notification::make()
            ->title(__('Message sent'))
            ->success()
            ->send();
    }
}
