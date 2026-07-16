<?php

namespace App\Filament\App\Pages;

use App\Enums\MenuGroupsEnum;
use App\Enums\UserSettingsEnum;
use App\Filament\App\Pages\Concerns\ManagesModelSettings;
use App\Filament\App\Resources\TaskResource;
use App\Helpers\PejotaHelper;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class MyPreferences extends Page implements HasForms
{
    use ManagesModelSettings;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?int $navigationSort = 97;

    protected string $view = 'filament.app.pages.model-settings';

    public static function getSettingRecord(): Model
    {
        return PejotaHelper::currentUser();
    }

    public static function getNavigationLabel(): string
    {
        return __('My preferences');
    }

    public function getTitle(): string|Htmlable
    {
        return self::getNavigationLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::SETTINGS->value);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Tabs::make('Tabs')->tabs([
                    Tab::make('Localization')
                        ->translateLabel()
                        ->schema([
                            Select::make(UserSettingsEnum::LOCALIZATION_LOCALE->value)
                                ->translateLabel()
                                ->label('Locale')
                                ->options(UserSettingsEnum::getLocales())
                                ->default('en'),

                            Select::make(UserSettingsEnum::LOCALIZATION_TIMEZONE->value)
                                ->translateLabel()
                                ->label('Timezone')
                                ->options(UserSettingsEnum::getTimezones())
                                ->default('UTC')
                                ->searchable(),

                            Select::make(UserSettingsEnum::LOCALIZATION_DATE_FORMAT->value)
                                ->translateLabel()
                                ->label('Date format')
                                ->options(UserSettingsEnum::getDateFormats())
                                ->default('d/m/Y'),

                            Select::make(UserSettingsEnum::LOCALIZATION_DATE_TIME_FORMAT->value)
                                ->translateLabel()
                                ->label('Date and time format')
                                ->options(UserSettingsEnum::getDateTimeFormats())
                                ->default('d/m/Y H:i:s'),
                        ]),

                    Tab::make('Tasks')
                        ->translateLabel()
                        ->schema([
                            CheckboxList::make(UserSettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value)
                                ->translateLabel()
                                ->options(
                                    collect(TaskResource::getTableColumns())
                                        ->mapWithKeys(function ($column) {
                                            return [
                                                $column->getName() => $column->getLabel(),
                                            ];
                                        })->toArray()
                                )
                                ->columns(2),
                        ]),
                ]),
            ]);
    }
}
