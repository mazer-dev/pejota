<?php

namespace App\Filament\App\Resources;

use AbdelhamidErrahmouni\FilamentMonacoEditor\MonacoEditor;
use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Filament\App\Resources\NoteResource\Pages;
use App\Models\Note;
use App\Tables\Columns\BlockTypesBadge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\ActionSize;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NoteResource extends Resource
{
    protected static ?string $model = Note::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

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
                ->url(Pages\EditNote::getUrl([$record->id]))
                ->icon('heroicon-o-pencil')
                ->size(ActionSize::ExtraSmall)
                ->tooltip(__('Edit note')),

            Action::make('view')
                ->hiddenLabel()
                ->url(Pages\ViewNote::getUrl([$record->id]))
                ->modal(true)
                ->icon(NoteResource::$navigationIcon)
                ->color(Color::Cyan)
                ->size(ActionSize::ExtraSmall)
                ->tooltip(__('View note')),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\Split::make([
                    Forms\Components\Section::make()->schema([
                        Forms\Components\TextInput::make('title')
                            ->translateLabel()
                            ->required(),

                        Forms\Components\Builder::make('content')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->reorderableWithButtons()
                            ->blocks([
                                Forms\Components\Builder\Block::make('link')
                                    ->columns(2)
                                    ->inlineLabel()
                                    ->icon('heroicon-o-link')
                                    ->schema([
                                        Forms\Components\TextInput::make('url')
                                            ->hiddenLabel()
                                            ->placeholder('url')
                                            ->required()
                                            ->prefixAction(
                                                fn($state) => Forms\Components\Actions\Action::make('url')
                                                    ->url($state)
                                                    ->openUrlInNewTab()
                                                    ->icon('heroicon-o-link')
                                            ),
                                        Forms\Components\TextInput::make('title')
                                            ->translateLabel()
                                            ->hiddenLabel()
                                            ->placeholder('Title'),
                                    ]),

                                Forms\Components\Builder\Block::make('code')
                                    ->translateLabel()
                                    ->icon('heroicon-o-code-bracket')
                                    ->schema([
                                        Forms\Components\Select::make('language')
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

                                        MonacoEditor::make('content')
                                            ->language(fn($get) => ($get('language') ?? 'html'))
                                            ->disablePreview(true)
                                            ->hideFullScreenButton()
                                            ->hiddenLabel(),
                                    ]),

                                Forms\Components\Builder\Block::make('markdown')
                                    ->icon('heroicon-o-code-bracket-square')
                                    ->schema([
                                        Forms\Components\MarkdownEditor::make('content')
                                            ->hiddenLabel(),
                                    ]),

                                Forms\Components\Builder\Block::make('richtext')
                                    ->icon('heroicon-o-code-bracket-square')
                                    ->schema([
                                        Forms\Components\RichEditor::make('content')
                                            ->hiddenLabel(),
                                    ]),

                                Forms\Components\Builder\Block::make('text')
                                    ->translateLabel()
                                    ->icon('heroicon-o-document-text')
                                    ->schema([
                                        Forms\Components\Textarea::make('content')
                                            ->hiddenLabel()
                                            ->rows(10),
                                    ]),
                            ])
                            ->addActionLabel(__('Add content type')),
                    ]),

                    Forms\Components\Section::make()->schema([
                        Forms\Components\SpatieTagsInput::make('tags'),

                        Forms\Components\Select::make('client')
                            ->translateLabel()
                            ->relationship('client', 'name')
                            ->preload()->searchable(),

                        Forms\Components\Select::make('project_id')
                            ->label('Project')
                            ->translateLabel()
                            ->relationship(
                                'project',
                                'name',
                                fn(Builder $query, Forms\Get $get) => $query->byClient($get('client'))->orderBy('name')
                            )
                            ->searchable()->preload(),
                    ])
                        ->grow(false),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->translateLabel()
                    ->searchable(),

                BlockTypesBadge::make('content')
                    ->translateLabel()
                    ->color(Color::Cyan),

                Tables\Columns\SpatieTagsColumn::make('tags'),

                Tables\Columns\TextColumn::make('client.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('project.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([

            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListNotes::route('/'),
            'create' => Pages\CreateNote::route('/create'),
            'view' => Pages\ViewNote::route('/{record}'),
            'edit' => Pages\EditNote::route('/{record}/edit'),
        ];
    }
}
