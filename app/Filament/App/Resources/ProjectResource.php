<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\App\Resources\ProjectResource\Pages\EditProject;
use App\Filament\App\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\App\Resources\ProjectResource\Pages\ViewProject;
use App\Helpers\PejotaHelper;
use App\Livewire\Projects\ListTasks;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\SpatieTagsEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?int $navigationSort = MenuSortEnum::PROJECTS->value;

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::ADMINISTRATION->value);
    }

    public static function getModelLabel(): string
    {
        return __('Project');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(
                self::getFormComponents()
            );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('active')
                    ->translateLabel(),

                SpatieTagsColumn::make('tags'),

                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->timezone(PejotaHelper::getUserTimeZone())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client')
                    ->relationship('client', 'name'),
                TernaryFilter::make('active'),
            ])
            ->groups([
                Group::make('client.name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc')
            ->persistFiltersInSession();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Flex::make([
                    Section::make([
                        TextEntry::make('name')
                            ->translateLabel()
                            ->size(TextSize::Large)
                            ->weight(FontWeight::Bold)
                            ->label(''),

                        SpatieTagsEntry::make('tags')
                            ->label(''),

                        TextEntry::make('client.name')
                            ->translateLabel()
                            ->label('')
                            ->icon('heroicon-o-building-office'),

                        TextEntry::make('description')
                            ->translateLabel()
                            ->formatStateUsing(fn (string $state): HtmlString => new HtmlString($state))
                            ->label('')
                            ->icon('heroicon-o-document-text'),

                    ]),

                    Section::make([
                        TextEntry::make('active')
                            ->translateLabel()
                            ->formatStateUsing(fn (string $state): string => $state ? __('Yes') : __('No')),

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

                Section::make('Tasks')
                    ->translateLabel()
                    ->schema([
                        Livewire::make(
                            ListTasks::class,
                            fn (Model $record) => ['project' => $record]
                        ),
                    ]),
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
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    public static function getFormComponents(): array
    {
        return [
            Select::make('client')
                ->translateLabel()
                ->relationship('client', 'name')
                ->preload(),
            TextInput::make('name')
                ->translateLabel()
                ->required(),
            TextInput::make('hourly_rate')
                ->translateLabel()
                ->numeric()
                ->minValue(0)
                ->helperText(__('Overrides the client rate for this project (optional)')),
            RichEditor::make('description')
                ->translateLabel()
                ->columnSpanFull()
                ->fileAttachmentsDisk('projects')
                ->fileAttachmentsDirectory(PejotaHelper::currentCompany()->id)
                ->fileAttachmentsVisibility('private'),
            SpatieTagsInput::make('tags'),
            Toggle::make('active')
                ->translateLabel()
                ->required()
                ->default(true),
        ];
    }
}
