<?php

namespace App\Filament\App\Pages;

use App\Enums\CompanySettingsEnum;
use App\Enums\MenuGroupsEnum;
use App\Filament\App\Pages\Concerns\ManagesModelSettings;
use App\Helpers\PejotaHelper;
use App\Models\Currency;
use App\Support\Help\HelpAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class CompanySettings extends Page implements HasForms
{
    use ManagesModelSettings;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.app.pages.model-settings';

    public static function getSettingRecord(): Model
    {
        return PejotaHelper::currentCompany();
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Tabs::make('Tabs')->tabs([
                    Tab::make('Clients')
                        ->translateLabel()
                        ->schema([
                            Checkbox::make(CompanySettingsEnum::CLIENT_PREFER_TRADENAME->value)
                                ->translateLabel()
                                ->helperText(
                                    __(
                                        'If checked the tradename will be used as the name of the client. Otherwise, the name will be used.'
                                    )
                                )
                                ->default(false),
                        ]),

                    Tab::make('Vendors')
                        ->translateLabel()
                        ->schema([
                            Checkbox::make(CompanySettingsEnum::VENDOR_PREFER_TRADENAME->value)
                                ->translateLabel()
                                ->helperText(
                                    __(
                                        'If checked the tradename will be used as the name of the vendor. Otherwise, the name will be used.'
                                    )
                                )
                                ->default(false),
                        ]),

                    Tab::make('Tasks')
                        ->translateLabel()
                        ->schema([
                            Checkbox::make(
                                CompanySettingsEnum::TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS->value
                            )
                                ->translateLabel()
                                ->helperText(
                                    __(
                                        'If checked when a task is updated with a status of in progress phase, if the actual start date is not set, then it will be filled with the date of update.'
                                    )
                                )
                                ->default(false),
                            Checkbox::make(
                                CompanySettingsEnum::TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED->value
                            )
                                ->translateLabel()
                                ->helperText(
                                    __(
                                        'If checked when a task is updated with a status of closed phase, if the actual end date is not set, then it will be filled with the date of update.'
                                    )
                                )
                                ->default(false),
                        ]),

                    Tab::make('Finance')
                        ->translateLabel()
                        ->schema([
                            Select::make(CompanySettingsEnum::FINANCE_CURRENCY->value)
                                ->translateLabel()
                                ->helperText(__('Set the default currency for the company.'))
                                ->options(fn (): array => $this->baseCurrencyOptions())
                                ->in(fn (): array => array_keys($this->baseCurrencyOptions()))
                                ->default(fn (): string => PejotaHelper::getUserCurrency()),
                        ]),

                    Tab::make('Invoices')
                        ->translateLabel()
                        ->schema([
                            TextInput::make(CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value)
                                ->translateLabel()
                                ->default(fn () => 'ym000')
                                ->live()
                                ->hintAction(
                                    HelpAction::form('invoice-number')->label('')
                                ),
                            Placeholder::make('format_preview')
                                ->label(__('Preview'))
                                ->content(function (Get $get): string {
                                    $format = $get(CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value);
                                    if (empty($format)) {
                                        return '—';
                                    }

                                    return CompanySettingsEnum::applyFormat($format, 1);
                                }),
                        ]),

                    Tab::make('Billing')
                        ->translateLabel()
                        ->schema([
                            TextInput::make(CompanySettingsEnum::BILLING_EMAIL_SUBJECT->value)
                                ->label('Email subject')
                                ->translateLabel()
                                ->hintAction(
                                    Action::make('billing_variables_help')
                                        ->icon('heroicon-o-question-mark-circle')
                                        ->label('')
                                        ->modalHeading(__('Available variables'))
                                        ->modalContent(new HtmlString(self::billingVariablesHelp()))
                                ),
                            RichEditor::make(CompanySettingsEnum::BILLING_EMAIL_BODY->value)
                                ->label('Email body')
                                ->translateLabel(),
                            RichEditor::make(CompanySettingsEnum::BILLING_EMAIL_SIGNATURE->value)
                                ->label('Email signature')
                                ->translateLabel(),
                            Textarea::make(CompanySettingsEnum::BILLING_WHATSAPP_TEMPLATE->value)
                                ->label('WhatsApp template')
                                ->translateLabel()
                                ->rows(4),
                        ]),

                ]),

            ]);
    }

    public static function billingVariablesHelp(): string
    {
        $vars = [
            'invoice.number', 'invoice.title', 'invoice.total', 'invoice.currency',
            'invoice.due_date', 'invoice.due_month',
            'client.name', 'client.tradename', 'company.name', 'user.name',
        ];

        $items = collect($vars)
            ->map(fn (string $v): string => '<li><code>{{ '.$v.' }}</code></li>')
            ->implode('');

        return '<ul class="space-y-1 text-sm">'.$items.'</ul>';
    }

    /**
     * Base currency options: active currencies plus the currently saved value.
     *
     * @return array<string, string>
     */
    public function baseCurrencyOptions(): array
    {
        return Currency::selectOptions(PejotaHelper::getUserCurrency());
    }
}
