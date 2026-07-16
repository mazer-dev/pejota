<?php

namespace App\Filament\App\Resources;

use App\Enums\FeatureEnum;
use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Concerns\GatesAccessByFeature;
use App\Filament\App\Resources\VendorResource\Pages\CreateVendor;
use App\Filament\App\Resources\VendorResource\Pages\EditVendor;
use App\Filament\App\Resources\VendorResource\Pages\ListVendors;
use App\Filament\App\Resources\VendorResource\Pages\ViewVendor;
use App\Helpers\PejotaHelper;
use App\Models\Vendor;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Parallax\FilamentComments\Infolists\Components\CommentsEntry;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class VendorResource extends Resource
{
    use GatesAccessByFeature;

    protected static ?string $model = Vendor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = MenuSortEnum::VENDORS->value;

    public static function feature(): FeatureEnum
    {
        return FeatureEnum::Vendors;
    }

    public static function getModelLabel(): string
    {
        return __('Vendor');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::ADMINISTRATION->value);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped(true)
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable(),
                TextColumn::make('tradename')
                    ->label(__('Tradename'))
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->searchable(),
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
            ->recordActions([
                ViewAction::make()
                    ->iconButton(),
                CommentsAction::make()
                    ->iconButton(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Flex::make([
                    Grid::make(1)
                        ->schema([
                            Section::make([
                                TextEntry::make('name')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::Bold)
                                    ->hiddenLabel(),

                                TextEntry::make('tradename')
                                    ->size(TextSize::Large)
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
            'index' => ListVendors::route('/'),
            'create' => CreateVendor::route('/create'),
            'view' => ViewVendor::route('/{record}'),
            'edit' => EditVendor::route('/{record}/edit'),
        ];
    }
}
