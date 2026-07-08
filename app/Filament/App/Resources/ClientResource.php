<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\ClientResource\Pages\CreateClient;
use App\Filament\App\Resources\ClientResource\Pages\EditClient;
use App\Filament\App\Resources\ClientResource\Pages\ListClients;
use App\Filament\App\Resources\ClientResource\Pages\ViewClient;
use App\Helpers\PejotaHelper;
use App\Jobs\GenerateClientAnalysis;
use App\Models\Client;
use App\Models\Currency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Parallax\FilamentComments\Infolists\Components\CommentsEntry;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = MenuSortEnum::CLIENTS->value;

    public static function getModelLabel(): string
    {
        return __('Client');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::ADMINISTRATION->value);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema(
                self::getSchema()
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped(true)
            ->defaultSort('name', 'asc')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('tradename')
                    ->label(__('Tradename'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable(),
                TextColumn::make('currency')
                    ->translateLabel()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Created at'))
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(__('Updated at'))
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),
                TableAction::make('linkWhatsapp')
                    ->label(__('Link WhatsApp'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->action(fn (Client $record) => WhatsappConversationResource::linkClientFromClientRecord($record)),
                CommentsAction::make()
                    ->iconButton(),
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
                Split::make([
                    Grid::make(1)
                        ->schema([
                            Section::make([
                                TextEntry::make('name')
                                    ->size(TextEntrySize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->hiddenLabel(),

                                TextEntry::make('tradename')
                                    ->size(TextEntrySize::Large)
                                    ->hiddenLabel()
                                    ->icon('heroicon-o-bookmark-square'),

                                TextEntry::make('email')
                                    ->hiddenLabel()
                                    ->icon('heroicon-o-at-symbol'),

                                TextEntry::make('phone')
                                    ->hiddenLabel()
                                    ->icon('heroicon-o-phone'),

                                TextEntry::make('ai_context')
                                    ->label(__('Client conversation context'))
                                    ->placeholder(__('No AI context registered.'))
                                    ->icon('heroicon-o-sparkles'),

                            ]),

                            Section::make(__('AI relationship analysis'))
                                ->key('aiAnalysisSection')
                                ->collapsible()
                                ->headerActions([
                                    Action::make('generateAnalysis')
                                        ->label(__('Generate new analysis'))
                                        ->icon('heroicon-o-sparkles')
                                        ->action(function (Client $record): void {
                                            GenerateClientAnalysis::dispatch($record, auth()->user());

                                            Notification::make()
                                                ->title(__('Analysis queued. You will be notified when it is ready.'))
                                                ->success()
                                                ->send();
                                        }),
                                ])
                                ->schema([
                                    TextEntry::make('latestAnalysis.created_at')
                                        ->label(__('Generated'))
                                        ->badge()
                                        ->color(Color::Gray)
                                        ->formatStateUsing(fn ($state): string => __('Analysis generated :time', [
                                            'time' => Carbon::parse($state)->locale(PejotaHelper::getUserLocate())->diffForHumans(),
                                        ]))
                                        ->visible(fn (Client $record): bool => $record->latestAnalysis !== null),

                                    TextEntry::make('latestAnalysis.content')
                                        ->hiddenLabel()
                                        ->markdown()
                                        ->placeholder(__('No analysis generated yet.')),
                                ]),

                            Section::make('Comments')
                                ->translateLabel()
                                ->collapsible()
                                ->schema([
                                    CommentsEntry::make('fialament_comments')
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Section::make([
                        TextEntry::make('created_at')
                            ->translateLabel()
                            ->dateTime()
                            ->timezone(PejotaHelper::getUserTimeZone()),
                        TextEntry::make('updated_at')
                            ->translateLabel()
                            ->dateTime()
                            ->timezone(PejotaHelper::getUserTimeZone()),
                        Actions::make([
                            Action::make('edit')
                                ->translateLabel()
                                ->url(
                                    fn (Model $record) => "{$record->id}/edit"
                                )
                                ->icon('heroicon-o-pencil'),

                            Action::make('back')
                                ->translateLabel()
                                ->url(
                                    fn (Model $record) => './.'
                                )
                                ->icon('heroicon-o-chevron-left')
                                ->color(Color::Neutral),
                        ]),
                    ])->grow(false),

                ])
                    ->from('md')
                    ->columnSpanFull(),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }

    public static function getSchema()
    {
        return [
            TextInput::make('name')
                ->label(__('Name'))
                ->required(),
            TextInput::make('tradename')
                ->label(__('Tradename')),
            TextInput::make('email')
                ->label(__('Email'))
                ->email(),
            TextInput::make('phone')
                ->label(__('Phone'))
                ->tel(),
            Select::make('currency')
                ->translateLabel()
                ->options(fn (): array => Currency::selectOptions())
                ->searchable()
                ->helperText(__("Default currency for this client's invoices")),
            TextInput::make('default_hourly_rate')
                ->translateLabel()
                ->numeric()
                ->minValue(0)
                ->helperText(__("Default hourly rate, in this client's currency")),
            Toggle::make('billable_default')
                ->translateLabel()
                ->default(true)
                ->helperText(__('New work sessions for this client are billable by default')),
            Textarea::make('ai_context')
                ->label(__('Client conversation context'))
                ->helperText(__('Background used by AI suggestions when writing to this client. Include how the relationship started, WhatsApp agreements, preferences and important constraints.'))
                ->rows(8)
                ->columnSpanFull(),
        ];
    }
}
