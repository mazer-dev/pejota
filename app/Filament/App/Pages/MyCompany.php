<?php

namespace App\Filament\App\Pages;

use App\Enums\MenuGroupsEnum;
use App\Enums\MenuSortEnum;
use App\Models\Company;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Js;
use Illuminate\Support\Facades\Auth;

class MyCompany extends Page implements HasForms
{
    use InteractsWithForms,
        InteractsWithFormActions;

    public ?array $data = [];

    public ?Company $company = null;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.app.pages.my-company';

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
        /** @var User $user */
        $user = Auth::user();
        
        if (!$user instanceof User) {
            return;
        }

        // Check if user has a company assigned
        if ($user->company_id) {
            $this->company = Company::find($user->company_id);
        }

        // If no company assigned, check if user has one via user_id
        if (!$this->company) {
            $this->company = Company::where('user_id', $user->id)->first();

            // If found, assign it to user
            if ($this->company) {
                User::where('id', $user->id)->update(['company_id' => $this->company->id]);
            }
        }

        // If still no company, create a new empty one for the form
        if (!$this->company) {
            $this->company = new Company([
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }

        $this->form->fill($this->company->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required(),

                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required(),

                TextInput::make('phone')
                    ->label(__('Phone'))
                    ->tel(),

                TextInput::make('website'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();

        if ($this->company && $this->company->exists) {
            // Update existing company
            $this->company->update($data);
        } else {
            // Create new company
            $data['user_id'] = $user->id;
            $this->company = Company::create($data);
        }

        // Assign company to user if not already assigned
        if ($user->company_id !== $this->company->id) {
            User::where('id', $user->id)->update(['company_id' => $this->company->id]);

            // Refresh the auth user session
            Auth::setUser(User::find($user->id));
        }

        Notification::make()
            ->title(__('Company saved successfully'))
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
            ->alpineClickHandler('document.referrer ? window.history.back() : (window.location.href = ' . Js::from($this->previousUrl ?? \Filament\Pages\Dashboard::getUrl()) . ')')
            ->color('gray');
    }
}