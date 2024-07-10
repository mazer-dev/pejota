<?php

namespace App\Filament\App\Pages;

use App\Enums\CompanySettingsEnum;
use App\Enums\MenuGroupsEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Contracts\Support\Htmlable;
use Quadrubo\FilamentModelSettings\Pages\Contracts\HasModelSettings;
use Quadrubo\FilamentModelSettings\Pages\ModelSettingsPage;

class CompanySettings extends ModelSettingsPage implements HasModelSettings
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    public static function getSettingRecord()
    {
        return auth()->user()->company;
    }

    public static function getNavigationLabel(): string
    {
        return __('Company settings');
    }

    public function getTitle(): string|Htmlable
    {
        return self::getNavigationLabel();
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::SETTINGS->value);
    }

    public function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->translateLabel();
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\Tabs::make('Tabs')->tabs([
                    Forms\Components\Tabs\Tab::make('Localization')
                        ->translateLabel()
                        ->schema([
                            Forms\Components\Select::make(CompanySettingsEnum::LOCALIZATION_LOCALE->value)
                                ->translateLabel()
                                ->label('Locale')
                                ->options(CompanySettingsEnum::getLocales())
                                ->default('en'),

                            Forms\Components\Select::make(CompanySettingsEnum::LOCALIZATION_TIMEZONE->value)
                                ->translateLabel()
                                ->label('Timezone')
                                ->options(CompanySettingsEnum::getTimezones())
                                ->default('UTC')
                                ->searchable(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Clients')
                        ->translateLabel()
                        ->schema([
                            Forms\Components\Checkbox::make(CompanySettingsEnum::CLIENT_PREFER_TRADENAME->value)
                                ->translateLabel()
                                ->helperText(__('If checked the tradename will be used as the name of the client. Otherwise, the name will be used.'))
                                ->default(false),
                        ]),

                    Forms\Components\Tabs\Tab::make('Tasks')
                        ->translateLabel()
                        ->schema([
                            Forms\Components\Checkbox::make(CompanySettingsEnum::TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS->value)
                                ->translateLabel()
                                ->helperText(__('If checked when a task is updated with a status of in progress phase, if the actual start date is not set, then it will be filled with the date of update.'))
                                ->default(false),
                            Forms\Components\Checkbox::make(CompanySettingsEnum::TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED->value)
                                ->translateLabel()
                                ->helperText(__('If checked when a task is updated with a status of closed phase, if the actual end date is not set, then it will be filled with the date of update.'))
                                ->default(false),
                        ]),

                    Forms\Components\Tabs\Tab::make('Finance')
                        ->translateLabel()
                        ->schema([
                            Forms\Components\Select::make(CompanySettingsEnum::FINANCE_CURRENCY->value)
                                ->translateLabel()
                                ->helperText(__('Set the default currency for the company.'))
                                ->default('USD')
                                ->options([
                                    'USD' => 'USD',
                                ]),
                        ]),
                ]),

            ]);
    }
}
