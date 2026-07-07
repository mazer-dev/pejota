<?php

namespace App\Filament\App\Pages;

use App\Enums\MailDriverEnum;
use App\Enums\MailEncryptionEnum;
use App\Enums\MenuGroupsEnum;
use App\Mail\TestMail;
use App\Models\Company;
use App\Models\CompanyMailConfig;
use App\Services\Mail\CompanyMailerFactory;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Js;

class CompanyMailSettings extends Page implements HasForms
{
    use InteractsWithFormActions,
        InteractsWithForms;

    public ?array $data = [];

    public Company $company;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static string $view = 'filament.app.pages.company-mail-settings';

    protected static ?int $navigationSort = 98;

    public function getTitle(): string|Htmlable
    {
        return __('Email settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Email settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(MenuGroupsEnum::SETTINGS->value);
    }

    public function mount(): void
    {
        $this->company = auth()->user()->company;
        $config = $this->company->mailConfig;

        $this->form->fill([
            'driver' => $config?->driver?->value ?? MailDriverEnum::Smtp->value,
            'host' => $config?->host,
            'port' => $config?->port,
            'encryption' => $config?->encryption?->value,
            'username' => $config?->username,
            // password intentionally omitted from the form on load (kept secret)
            'from_address' => $config?->from_address ?? $this->company->email,
            'from_name' => $config?->from_name ?? $this->company->name,
            'reply_to' => $config?->reply_to ?? $this->company->email,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('driver')
                    ->label(__('Driver'))
                    ->options(MailDriverEnum::class)
                    ->default(MailDriverEnum::Smtp->value)
                    ->required(),
                TextInput::make('host')
                    ->label(__('SMTP host'))
                    ->required(),
                TextInput::make('port')
                    ->label(__('Port'))
                    ->numeric()
                    ->required(),
                Select::make('encryption')
                    ->label(__('Encryption'))
                    ->options(MailEncryptionEnum::class)
                    ->placeholder(__('None')),
                TextInput::make('username')
                    ->label(__('Username'))
                    ->required(),
                TextInput::make('password')
                    ->label(__('Password'))
                    ->password()
                    ->revealable()
                    ->placeholder('•••• '.__('(unchanged)'))
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                TextInput::make('from_address')
                    ->label(__('From address'))
                    ->email()
                    ->required(),
                TextInput::make('from_name')
                    ->label(__('From name')),
                TextInput::make('reply_to')
                    ->label(__('Reply-to'))
                    ->email(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function save(): void
    {
        $this->company->mailConfig()->updateOrCreate([], $this->form->getState());

        Notification::make()
            ->title(__('Email settings saved'))
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label(__('Send test email'))
                ->icon('heroicon-o-paper-airplane')
                ->form([
                    TextInput::make('test_recipient')
                        ->label(__('Send to'))
                        ->email()
                        ->required()
                        ->default(fn (): ?string => auth()->user()->email),
                ])
                ->action(function (array $data): void {
                    $state = $this->form->getState();
                    $stored = $this->company->mailConfig;

                    $config = new CompanyMailConfig([
                        'driver' => $state['driver'] ?? 'smtp',
                        'host' => $state['host'] ?? null,
                        'port' => $state['port'] ?? null,
                        'encryption' => $state['encryption'] ?? null,
                        'username' => $state['username'] ?? null,
                        'password' => $state['password'] ?? $stored?->password,
                        'from_address' => $state['from_address'] ?? null,
                        'from_name' => $state['from_name'] ?? null,
                        'reply_to' => $state['reply_to'] ?? null,
                    ]);

                    try {
                        $mailer = app(CompanyMailerFactory::class)->build($config);

                        Mail::mailer($mailer)
                            ->to($data['test_recipient'])
                            ->send(new TestMail(
                                $config->from_address,
                                $config->from_name,
                                $config->reply_to,
                            ));

                        Notification::make()
                            ->title(__('Test email sent'))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('Test email failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
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
