<?php

namespace App\Filament\App\Pages;

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
                Forms\Components\Checkbox::make('clients.prefer_tradename')
                    ->helperText('If checked the tradename will be used as the name of the client. Otherwise, the name will be used.')
                    ->default(false),
            ]);
    }
}
