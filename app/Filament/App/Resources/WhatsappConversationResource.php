<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Filament\App\Resources\WhatsappConversationResource\Pages\CreateWhatsappConversation;
use App\Filament\App\Resources\WhatsappConversationResource\Pages\EditWhatsappConversation;
use App\Filament\App\Resources\WhatsappConversationResource\Pages\ListWhatsappConversations;
use App\Filament\App\Resources\WhatsappConversationResource\Pages\ViewWhatsappConversation;
use App\Filament\App\Resources\WhatsappConversationResource\RelationManagers\MessagesRelationManager;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Project;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Evolution\EvolutionApiClient;
use App\Services\Evolution\WhatsappConversationMatcher;
use App\Services\Evolution\WhatsappConversationTokenService;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class WhatsappConversationResource extends Resource
{
    protected static ?string $model = WhatsappConversation::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 45;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getModelLabel(): string
    {
        return __('WhatsApp conversation');
    }

    public static function getPluralModelLabel(): string
    {
        return __('WhatsApp conversations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::DAILY_WORK->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(2)
            ->schema([
                Select::make('client_id')
                    ->label(__('Client'))
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?int $state): void {
                        $set('project_id', null);

                        if (! $state) {
                            return;
                        }

                        $client = Client::find($state);
                        if (! $client) {
                            return;
                        }

                        $set('push_name', $client->name ?: $client->tradename);
                        $set('phone_number', $client->phone);
                        $set('remote_jid', self::remoteJidFromPhone($client->phone));
                    }),
                Select::make('project_id')
                    ->label(__('Project'))
                    ->options(fn (Get $get): array => self::projectOptions($get('client_id')))
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => blank($get('client_id'))),
                TextInput::make('push_name')
                    ->label(__('Name')),
                TextInput::make('phone_number')
                    ->label(__('Phone'))
                    ->tel()
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('remote_jid', self::remoteJidFromPhone($state));
                    }),
                Select::make('evolution_instance')
                    ->label(__('Evolution instance'))
                    ->options(fn () => app(EvolutionApiClient::class)->instanceOptions())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(config('services.evolution.instance')),
                Hidden::make('remote_jid')
                    ->dehydrateStateUsing(fn (?string $state, Get $get): ?string => $state ?: self::remoteJidFromPhone($get('phone_number'))),
                Select::make('status')
                    ->label(__('Status'))
                    ->options([
                        'open' => __('Opened'),
                        'closed' => __('Closed'),
                    ])
                    ->default('open')
                    ->required(),
                Textarea::make('notes')
                    ->label(__('Internal observations'))
                    ->rows(5)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('Conversation'))
                    ->searchable(['push_name', 'phone_number', 'remote_jid']),
                TextColumn::make('evolution_instance')
                    ->label(__('Evolution instance'))
                    ->badge()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('client.name')
                    ->label(__('Client'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('context_tokens')
                    ->label(__('Context tokens'))
                    ->numeric()
                    ->sortable()
                    ->badge(),
                TextColumn::make('unread_count')
                    ->label(__('Unread'))
                    ->numeric()
                    ->sortable()
                    ->badge(),
                TextColumn::make('last_message_at')
                    ->label(__('Last message'))
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
            ])
            ->actions([
                Action::make('sendMessage')
                    ->label(__('Send message'))
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Textarea::make('message')
                            ->label(__('Message'))
                            ->required()
                            ->rows(5),
                    ])
                    ->action(fn (WhatsappConversation $record, array $data) => self::sendMessage($record, (string) $data['message'])),
                Action::make('linkClient')
                    ->label(__('Link client'))
                    ->icon('heroicon-o-link')
                    ->action(fn (WhatsappConversation $record) => self::linkClient($record)),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('Conversation'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('display_name')
                            ->label(__('Name')),
                        TextEntry::make('client.name')
                            ->label(__('Client'))
                            ->placeholder('-'),
                        TextEntry::make('project.name')
                            ->label(__('Project'))
                            ->placeholder('-'),
                        TextEntry::make('phone_number')
                            ->label(__('Phone'))
                            ->placeholder('-'),
                        TextEntry::make('evolution_instance')
                            ->label(__('Evolution instance')),
                        TextEntry::make('context_tokens')
                            ->label(__('Context tokens'))
                            ->badge(),
                        TextEntry::make('context_updated_at')
                            ->label(__('Context updated at'))
                            ->dateTime()
                            ->timezone(PejotaHelper::getUserTimeZone())
                            ->placeholder('-'),
                    ]),
                Section::make(__('Internal observations'))
                    ->schema([
                        TextEntry::make('notes')
                            ->hiddenLabel()
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function projectOptions(null|int|string $clientId): array
    {
        if (blank($clientId)) {
            return [];
        }

        return Project::query()
            ->where('client_id', $clientId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareConversationData(array $data): array
    {
        $client = null;
        if (! empty($data['client_id'])) {
            $client = Client::find($data['client_id']);
        }

        if ($client) {
            $data['push_name'] = $data['push_name'] ?: ($client->name ?: $client->tradename);
            $data['phone_number'] = $data['phone_number'] ?: $client->phone;
        }

        $data['remote_jid'] = $data['remote_jid'] ?: self::remoteJidFromPhone($data['phone_number'] ?? null);

        return $data;
    }

    public static function remoteJidFromPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits ? "{$digits}@s.whatsapp.net" : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappConversations::route('/'),
            'create' => CreateWhatsappConversation::route('/create'),
            'view' => ViewWhatsappConversation::route('/{record}'),
            'edit' => EditWhatsappConversation::route('/{record}/edit'),
        ];
    }

    private static function sendMessage(WhatsappConversation $record, string $text): void
    {
        $response = app(EvolutionApiClient::class)->sendText($record, $text);
        $messageId = data_get($response, 'key.id')
            ?: data_get($response, 'message.key.id')
            ?: data_get($response, 'data.key.id')
            ?: 'local-'.Str::uuid();

        WhatsappMessage::create([
            'company_id' => $record->company_id,
            'whatsapp_conversation_id' => $record->id,
            'client_id' => $record->client_id,
            'project_id' => $record->project_id,
            'evolution_instance' => $record->evolution_instance,
            'remote_message_id' => $messageId,
            'remote_jid' => $record->remote_jid,
            'from_me' => true,
            'message_type' => 'text',
            'text' => $text,
            'status' => 'sent',
            'sent_at' => now(),
            'payload' => $response,
        ]);

        $record->forceFill([
            'last_message_at' => now(),
        ])->save();

        app(WhatsappConversationTokenService::class)->refresh($record);

        Notification::make()
            ->title(__('Message sent'))
            ->success()
            ->send();
    }

    private static function linkClient(WhatsappConversation $record): void
    {
        $client = app(WhatsappConversationMatcher::class)->linkConversation($record);
        if ($client) {
            app(WhatsappConversationTokenService::class)->refresh($record);
        }

        $notification = Notification::make()
            ->title($client ? __('Client linked') : __('No compatible client found'))
            ->body($client ? $client->name : null);

        ($client ? $notification->success() : $notification->warning())->send();
    }

    public static function linkClientFromClientRecord(Client $client): void
    {
        $conversation = app(WhatsappConversationMatcher::class)->linkClient($client);

        if ($conversation) {
            app(WhatsappConversationTokenService::class)->refresh($conversation);
        }

        $notification = Notification::make()
            ->title($conversation ? __('WhatsApp conversation linked') : __('No compatible WhatsApp conversation found'))
            ->body($conversation ? $conversation->display_name : null);

        ($conversation ? $notification->success() : $notification->warning())->send();
    }
}
