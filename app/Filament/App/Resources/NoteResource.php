<?php

namespace App\Filament\App\Resources;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\NoteResource\Pages\CreateNote;
use App\Filament\App\Resources\NoteResource\Pages\EditNote;
use App\Filament\App\Resources\NoteResource\Pages\ListNotes;
use App\Filament\App\Resources\NoteResource\Pages\ViewNote;
use App\Models\Note;
use App\Tables\Columns\BlockTypesBadge;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Size;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?int $navigationSort = MenuSortEnum::NOTES->value;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('Note');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Notes');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::DAILY_WORK->value);
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('edit')
                ->hiddenLabel()
                ->url(EditNote::getUrl([$record->id]))
                ->icon('heroicon-o-pencil')
                ->size(Size::ExtraSmall)
                ->tooltip(__('Edit note')),

            Action::make('view')
                ->hiddenLabel()
                ->url(ViewNote::getUrl([$record->id]))
                ->modal(true)
                ->icon(NoteResource::$navigationIcon)
                ->color(Color::Cyan)
                ->size(Size::ExtraSmall)
                ->tooltip(__('View note')),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Flex::make([
                    Section::make()->schema([
                        TextInput::make('title')
                            ->translateLabel()
                            ->required(),

                        Forms\Components\Builder::make('content')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->reorderableWithButtons()
                            ->blocks([
                                Block::make('link')
                                    ->columns(2)
                                    ->inlineLabel()
                                    ->icon('heroicon-o-link')
                                    ->schema([
                                        TextInput::make('url')
                                            ->hiddenLabel()
                                            ->placeholder('url')
                                            ->required()
                                            ->prefixAction(
                                                fn ($state) => Action::make('url')
                                                    ->url($state)
                                                    ->openUrlInNewTab()
                                                    ->icon('heroicon-o-link')
                                            ),
                                        TextInput::make('title')
                                            ->translateLabel()
                                            ->hiddenLabel()
                                            ->placeholder('Title'),
                                    ]),

                                Block::make('code')
                                    ->translateLabel()
                                    ->icon('heroicon-o-code-bracket')
                                    ->schema([
                                        Select::make('language')
                                            ->translateLabel()
                                            ->options([
                                                'bash' => 'Bash',
                                                'bat' => 'Batch',
                                                'c' => 'C',
                                                'csharp' => 'C#',
                                                'css' => 'CSS',
                                                'csv' => 'CSV',
                                                'dart' => 'Dart',
                                                'diff' => 'Diff',
                                                'dockerfile' => 'Dockerfile',
                                                'elixir' => 'Elixir',
                                                'go' => 'Go',
                                                'html' => 'HTML',
                                                'ini' => 'INI',
                                                'java' => 'Java',
                                                'javascript' => 'JavaScript',
                                                'json' => 'JSON',
                                                'kotlin' => 'Kotlin',
                                                'less' => 'Less',
                                                'lua' => 'Lua',
                                                'markdown' => 'Markdown',
                                                'perl' => 'Perl',
                                                'php' => 'PHP',
                                                'python' => 'Python',
                                                'redis' => 'Redis',
                                                'ruby' => 'Ruby',
                                                'rust' => 'Rust',
                                                'scss' => 'SCSS',
                                                'sql' => 'SQL',
                                                'swift' => 'Swift',
                                                'typescript' => 'TypeScript',
                                                'xml' => 'XML',
                                                'yaml' => 'YAML',
                                            ])
                                            ->live(),

                                        Textarea::make('content')
                                            ->hiddenLabel()
                                            ->rows(15)
                                            ->autosize(),
                                    ]),

                                Block::make('markdown')
                                    ->icon('heroicon-o-code-bracket-square')
                                    ->schema([
                                        MarkdownEditor::make('content')
                                            ->hiddenLabel(),
                                    ]),

                                Block::make('richtext')
                                    ->icon('heroicon-o-code-bracket-square')
                                    ->schema([
                                        RichEditor::make('content')
                                            ->hiddenLabel(),
                                    ]),

                                Block::make('text')
                                    ->translateLabel()
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        Textarea::make('content')
                                            ->hiddenLabel()
                                            ->rows(10),
                                    ]),
                            ])
                            ->addActionLabel(__('Add content type')),
                    ]),

                    Section::make()->schema([
                        SpatieTagsInput::make('tags'),

                        Select::make('client')
                            ->translateLabel()
                            ->relationship('client', 'name')
                            ->preload()->searchable(),

                        Select::make('project_id')
                            ->label('Project')
                            ->translateLabel()
                            ->relationship(
                                'project',
                                'name',
                                fn (Builder $query, Get $get) => $query->byClient($get('client'))->orderBy('name')
                            )
                            ->searchable()->preload(),
                    ])
                        ->grow(false),
                ])
                    ->from('md'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                TextColumn::make('title')
                    ->translateLabel()
                    ->searchable(),

                BlockTypesBadge::make('content')
                    ->translateLabel()
                    ->color(Color::Cyan),

                SpatieTagsColumn::make('tags'),

                TextColumn::make('client.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('project.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([

            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListNotes::route('/'),
            'create' => CreateNote::route('/create'),
            'view' => ViewNote::route('/{record}'),
            'edit' => EditNote::route('/{record}/edit'),
        ];
    }
}
