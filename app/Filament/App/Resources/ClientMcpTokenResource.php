<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\ClientMcpTokenResource\Pages\CreateClientMcpToken;
use App\Filament\App\Resources\ClientMcpTokenResource\Pages\ListClientMcpTokens;
use App\Filament\App\Resources\ClientMcpTokenResource\Pages\ViewClientMcpToken;
use App\Mcp\ClientMcpConnection;
use App\Models\ClientMcpToken;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientMcpTokenResource extends Resource
{
    protected static ?string $model = ClientMcpToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?int $navigationSort = MenuSortEnum::MCP_ACCESS->value;

    public static function getModelLabel(): string
    {
        return __('MCP access');
    }

    public static function getPluralModelLabel(): string
    {
        return __('MCP accesses');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::SETTINGS->value);
    }

    /**
     * Shows at a glance how many clients are currently exposed, read only,
     * through MCP.
     */
    public static function getNavigationBadge(): ?string
    {
        $clients = static::getEloquentQuery()
            ->whereNull('revoked_at')
            ->distinct()
            ->count('client_id');

        return $clients > 0 ? (string) $clients : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Clients exposed read-only via MCP');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Select::make('client_id')
                    ->label(__('Client'))
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText(__('This connection will only ever read data from this client.')),

                TextInput::make('name')
                    ->label(__('Access name'))
                    ->default('Claude Code')
                    ->required()
                    ->maxLength(255)
                    ->helperText(__('Where you are going to use it, so you know what to revoke later.')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('client.name')
                    ->label(__('Client'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label(__('Access name'))
                    ->searchable(),

                TextColumn::make('permissions')
                    ->label(__('Permissions'))
                    ->badge()
                    ->color('success')
                    ->state(fn (): string => __('Read only')),

                TextColumn::make('revoked_at')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (ClientMcpToken $record): string => $record->isActive() ? 'success' : 'danger')
                    ->state(fn (ClientMcpToken $record): string => $record->isActive() ? __('Active') : __('Revoked')),

                TextColumn::make('last_used_at')
                    ->label(__('Last used'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('Never used'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label(__('Active'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Active'))
                    ->falseLabel(__('Revoked'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('revoked_at'),
                        false: fn (Builder $query) => $query->whereNotNull('revoked_at'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('Connection manual')),

                Action::make('revoke')
                    ->label(__('Revoke'))
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('The agent using this token loses access immediately.'))
                    ->visible(fn (ClientMcpToken $record): bool => $record->isActive())
                    ->action(function (ClientMcpToken $record): void {
                        $record->update(['revoked_at' => now()]);

                        Notification::make()
                            ->success()
                            ->title(__('Access revoked'))
                            ->send();
                    }),
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
                Section::make(__('Access'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('client.name')
                            ->label(__('Client')),

                        TextEntry::make('name')
                            ->label(__('Access name')),

                        TextEntry::make('permissions')
                            ->label(__('Permissions'))
                            ->badge()
                            ->color('success')
                            ->state(__('Read only')),

                        TextEntry::make('revoked_at')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (ClientMcpToken $record): string => $record->isActive() ? 'success' : 'danger')
                            ->state(fn (ClientMcpToken $record): string => $record->isActive() ? __('Active') : __('Revoked')),

                        TextEntry::make('last_used_at')
                            ->label(__('Last used'))
                            ->dateTime('d/m/Y H:i')
                            ->placeholder(__('Never used')),

                        TextEntry::make('created_at')
                            ->label(__('Created at'))
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make(__('Your token'))
                    ->description(__('It is shown only now. If you lose it, revoke this access and create another one.'))
                    ->visible(fn ($livewire): bool => static::plainTokenFrom($livewire) !== null)
                    ->schema([
                        TextEntry::make('plain_token')
                            ->hiddenLabel()
                            ->copyable()
                            ->copyMessage(__('Token copied'))
                            ->weight('bold')
                            ->state(fn ($livewire): string => (string) static::plainTokenFrom($livewire)),
                    ]),

                Section::make(__('How to connect'))
                    ->description(__('Any agent that speaks MCP over HTTP connects with the URL plus the token header.'))
                    ->schema([
                        TextEntry::make('endpoint')
                            ->label(__('Endpoint'))
                            ->copyable()
                            ->state(fn (): string => ClientMcpConnection::endpoint()),

                        TextEntry::make('claude_command')
                            ->label(__('Claude Code'))
                            ->copyable()
                            ->copyableState(fn (ClientMcpToken $record, $livewire): string => ClientMcpConnection::claudeCommand(
                                $record,
                                static::plainTokenFrom($livewire)
                            ))
                            ->html()
                            ->state(fn (ClientMcpToken $record, $livewire): string => static::codeBlock(
                                ClientMcpConnection::claudeCommand($record, static::plainTokenFrom($livewire))
                            ))
                            ->columnSpanFull(),

                        TextEntry::make('json_config')
                            ->label(__('Configuration file (.mcp.json, codex, agy)'))
                            ->copyable()
                            ->copyableState(fn (ClientMcpToken $record, $livewire): string => ClientMcpConnection::jsonConfig(
                                $record,
                                static::plainTokenFrom($livewire)
                            ))
                            ->html()
                            ->state(fn (ClientMcpToken $record, $livewire): string => static::codeBlock(
                                ClientMcpConnection::jsonConfig($record, static::plainTokenFrom($livewire))
                            ))
                            ->columnSpanFull(),
                    ]),

                Section::make(__('What this connection can do'))
                    ->schema([
                        TextEntry::make('capabilities')
                            ->hiddenLabel()
                            ->markdown()
                            ->state(fn (ClientMcpToken $record): string => ClientMcpConnection::capabilities($record))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Keeps line breaks and monospace on the snippets, which HTML would
     * otherwise collapse into a single unreadable line.
     */
    protected static function codeBlock(string $content): string
    {
        return '<pre style="white-space: pre-wrap; word-break: break-word; margin: 0; '
            .'font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.8rem; line-height: 1.5;">'
            .e($content)
            .'</pre>';
    }

    protected static function plainTokenFrom(mixed $livewire): ?string
    {
        $token = data_get($livewire, 'plainToken');

        return is_string($token) && $token !== '' ? $token : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClientMcpTokens::route('/'),
            'create' => CreateClientMcpToken::route('/create'),
            'view' => ViewClientMcpToken::route('/{record}'),
        ];
    }
}
