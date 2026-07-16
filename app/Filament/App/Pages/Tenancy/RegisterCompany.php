<?php

namespace App\Filament\App\Pages\Tenancy;

use App\Services\CompanyService;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return __('Register company');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('Name'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('Email'))
                ->email()
                ->maxLength(255),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        return app(CompanyService::class)->create(
            auth()->user(),
            $data['name'],
            $data['email'] ?? null,
        );
    }
}
