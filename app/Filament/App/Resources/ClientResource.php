<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Pages\CompanySettings;
use App\Filament\App\Resources\ClientResource\Pages\CreateClient;
use App\Filament\App\Resources\ClientResource\Pages\EditClient;
use App\Filament\App\Resources\ClientResource\Pages\ListClients;
use App\Filament\App\Resources\ClientResource\Pages\ViewClient;
use App\Helpers\PejotaHelper;
use App\Models\Client;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
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
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
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

    public static function getSchema(): array
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
            Forms\Components\Section::make('Billing')
                ->translateLabel()
                ->columns(1)
                ->schema([
                    Toggle::make('bill_by_email')
                        ->label('Send invoices by email')
                        ->translateLabel()
                        ->default(true),
                    Toggle::make('bill_by_whatsapp')
                        ->label('Send invoices by WhatsApp')
                        ->translateLabel()
                        ->default(false),
                    Repeater::make('contacts')
                        ->translateLabel()
                        ->relationship()
                        ->schema([
                            TextInput::make('name')
                                ->label(__('Name'))
                                ->required(),
                            TextInput::make('email')
                                ->label(__('Email'))
                                ->email(),
                            TextInput::make('whatsapp')
                                ->label(__('WhatsApp'))
                                ->tel(),
                            Toggle::make('receives_billing')
                                ->label('Receives billing')
                                ->translateLabel(),
                        ])
                        ->columns(2)
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel(__('Add contact')),
                    Forms\Components\Section::make('Email template overrides')
                        ->translateLabel()
                        ->description(__('Leave blank to use the company default.'))
                        ->collapsed()
                        ->schema([
                            TextInput::make('billing_email_subject')
                                ->label('Email subject')
                                ->translateLabel()
                                ->hintAction(
                                    FormAction::make('client_billing_vars')
                                        ->icon('heroicon-o-question-mark-circle')
                                        ->label('')
                                        ->modalHeading(__('Available variables'))
                                        ->modalContent(new HtmlString(CompanySettings::billingVariablesHelp()))
                                ),
                            RichEditor::make('billing_email_body')
                                ->label('Email body')
                                ->translateLabel(),
                            RichEditor::make('billing_email_signature')
                                ->label('Email signature')
                                ->translateLabel(),
                            Textarea::make('billing_whatsapp_template')
                                ->label('WhatsApp template')
                                ->translateLabel()
                                ->rows(4),
                        ]),
                ]),
        ];
    }
}
