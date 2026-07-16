<?php

namespace App\Filament\App\Pages;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Helpers\PejotaHelper;
use App\Models\Company;
use Filament\Actions\Action;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Js;

class MyCompany extends Page implements HasForms
{
    use InteractsWithFormActions,
        InteractsWithForms;

    public ?array $data = [];

    public Company $company;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.app.pages.my-company';

    protected static ?int $navigationSort = MenuSortEnum::MY_COMPANY->value;

    public function getTitle(): string|Htmlable
    {
        return __('My company');
    }

    public static function getModelLabel(): string
    {
        return __('My company');
    }

    public static function getNavigationLabel(): string
    {
        return __('My company');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::ADMINISTRATION->value);
    }

    public function mount(): void
    {
        $this->company = PejotaHelper::currentCompany();
        $this->form->fill($this->company->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email(),
                TextInput::make('phone')
                    ->label(__('Phone'))
                    ->tel(),
                TextInput::make('website'),
                SpatieMediaLibraryFileUpload::make('logo')
                    ->translateLabel()
                    ->disk('companies-logo')
                    ->visibility('public')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios([
                        '16:9',
                        '4:3',
                        '1:1',
                    ]),
            ])
            ->statePath('data')
            ->model($this->company);
    }

    public function save(): void
    {
        $this->company->update($this->form->getState());

        Notification::make()
            ->title(__('Company updated'))
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    protected function getSubmitFormAction(): Action
    {
        return $this->getSaveFormAction();
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.cancel.label'))
            ->alpineClickHandler('document.referrer ? window.history.back() : (window.location.href = '.Js::from($this->previousUrl ?? Dashboard::getUrl()).')')
            ->color('gray');
    }
}
