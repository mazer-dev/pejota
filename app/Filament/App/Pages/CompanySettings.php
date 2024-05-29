<?php

namespace App\Filament\App\Pages;

use App\Enums\CompanySettingsEnum;
use Filament\Forms;
use Filament\Forms\Form;
use Quadrubo\FilamentModelSettings\Pages\ModelSettingsPage;
use Quadrubo\FilamentModelSettings\Pages\Contracts\HasModelSettings;

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
        return __('Settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Administration');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Clients')->schema([
                    Forms\Components\Checkbox::make(CompanySettingsEnum::CLIENT_PREFER_TRADENAME->value)
                        ->helperText('If checked the tradename will be used as the name of the client. Otherwise, the name will be used.')
                        ->default(false),
                ])
                    ->collapsible()->collapsed(),

                Forms\Components\Section::make('Tasks')->schema([
                    Forms\Components\Checkbox::make(CompanySettingsEnum::TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS->value)
                        ->helperText('If checked when a task is updated with a status of in progress phase,
                            if the actual start date is not set, then it will be filled with the date of update.')
                        ->default(false),
                    Forms\Components\Checkbox::make(CompanySettingsEnum::TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED->value)
                        ->helperText('If checked when a task is updated with a status of closed phase,
                            if the actual end date is not set, then it will be filled with the date of update.')
                        ->default(false),
                ])
                    ->collapsible()->collapsed(),

                Forms\Components\Section::make('Finance')->schema([
                    Forms\Components\Select::make(CompanySettingsEnum::FINANCE_DEFAULT_CURRENCY->value)
                        ->helperText('Set the default currency for the company.')
                        ->default('USD')
                        ->options([
                            'USD' => 'USD',
                        ]),
                ])
                    ->collapsible()->collapsed(),
            ]);
    }
}
