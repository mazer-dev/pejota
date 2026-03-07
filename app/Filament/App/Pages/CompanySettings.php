<?php

namespace App\Filament\App\Pages;

use App\Enums\CompanySettingsEnum;
use App\Enums\MenuGroupsEnum;
use App\Filament\App\Resources\TaskResource;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Form;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
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

                            Forms\Components\Select::make(CompanySettingsEnum::LOCALIZATION_DATE_FORMAT->value)
                                ->translateLabel()
                                ->label('Date format')
                                ->options(CompanySettingsEnum::getDateFormats())
                                ->default('d/m/Y'),

                            Forms\Components\Select::make(CompanySettingsEnum::LOCALIZATION_DATE_TIME_FORMAT->value)
                                ->translateLabel()
                                ->label('Date and time format')
                                ->options(CompanySettingsEnum::getDateTimeFormats())
                                ->default('d/m/Y H:i:s'),
                        ]),

                    Forms\Components\Tabs\Tab::make('Clients')
                        ->translateLabel()
                        ->schema([
                            Forms\Components\Checkbox::make(CompanySettingsEnum::CLIENT_PREFER_TRADENAME->value)
                                ->translateLabel()
                                ->helperText(
                                    __(
                                        'If checked the tradename will be used as the name of the client. Otherwise, the name will be used.'
                                    )
                                )
                                ->default(false),
                        ]),

                    Forms\Components\Tabs\Tab::make('Vendors')
                        ->translateLabel()
                        ->schema([
                            Forms\Components\Checkbox::make(CompanySettingsEnum::VENDOR_PREFER_TRADENAME->value)
                                ->translateLabel()
                                ->helperText(
                                    __(
                                        'If checked the tradename will be used as the name of the vendor. Otherwise, the name will be used.'
                                    )
                                )
                                ->default(false),
                        ]),

                    Forms\Components\Tabs\Tab::make('Tasks')
                        ->translateLabel()
                        ->schema([
                            Forms\Components\Checkbox::make(
                                CompanySettingsEnum::TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS->value
                            )
                                ->translateLabel()
                                ->helperText(
                                    __(
                                        'If checked when a task is updated with a status of in progress phase, if the actual start date is not set, then it will be filled with the date of update.'
                                    )
                                )
                                ->default(false),
                            Forms\Components\Checkbox::make(
                                CompanySettingsEnum::TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED->value
                            )
                                ->translateLabel()
                                ->helperText(
                                    __(
                                        'If checked when a task is updated with a status of closed phase, if the actual end date is not set, then it will be filled with the date of update.'
                                    )
                                )
                                ->default(false),

                            Forms\Components\CheckboxList::make(CompanySettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value)
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

                    Forms\Components\Tabs\Tab::make('Invoices')
                        ->translateLabel()
                        ->schema([
                            Forms\Components\TextInput::make(CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value)
                                ->translateLabel()
                                ->default(fn () => 'ym000')
                                ->live()
                                ->hintAction(
                                    FormAction::make('format_help')
                                        ->icon('heroicon-o-question-mark-circle')
                                        ->label('')
                                        ->modalHeading(__('Invoice Number Format'))
                                        ->modalContent(new HtmlString('
                                            <div class="space-y-4 text-sm">
                                                <p>'.__('Build the invoice number using date tokens and zero-padding for the sequence.').'</p>
                                                <table class="w-full text-left border-collapse">
                                                    <thead>
                                                        <tr class="border-b dark:border-gray-700">
                                                            <th class="py-2 pr-4 font-semibold">'.__('Token').'</th>
                                                            <th class="py-2 pr-4 font-semibold">'.__('Meaning').'</th>
                                                            <th class="py-2 font-semibold">'.__('Example').'</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y dark:divide-gray-700">
                                                        <tr><td class="py-2 pr-4 font-mono">y</td><td class="py-2 pr-4">'.__('2-digit year').'</td><td class="py-2 font-mono">26</td></tr>
                                                        <tr><td class="py-2 pr-4 font-mono">Y</td><td class="py-2 pr-4">'.__('4-digit year').'</td><td class="py-2 font-mono">2026</td></tr>
                                                        <tr><td class="py-2 pr-4 font-mono">m</td><td class="py-2 pr-4">'.__('Month (01–12)').'</td><td class="py-2 font-mono">03</td></tr>
                                                        <tr><td class="py-2 pr-4 font-mono">d</td><td class="py-2 pr-4">'.__('Day (01–31)').'</td><td class="py-2 font-mono">07</td></tr>
                                                        <tr><td class="py-2 pr-4 font-mono">000</td><td class="py-2 pr-4">'.__('3-digit sequence (use as many zeros as needed)').'</td><td class="py-2 font-mono">001</td></tr>
                                                    </tbody>
                                                </table>
                                                <div class="space-y-1 pt-2">
                                                    <p class="font-semibold">'.__('Examples').'</p>
                                                    <ul class="space-y-1 font-mono text-gray-600 dark:text-gray-400">
                                                        <li>ym000 → 2603001 <span class="font-sans text-xs">('.__('resets monthly').')</span></li>
                                                        <li>Ym000 → 202603001 <span class="font-sans text-xs">('.__('resets monthly, 4-digit year').')</span></li>
                                                        <li>Y000 → 2026001 <span class="font-sans text-xs">('.__('resets yearly').')</span></li>
                                                        <li>ymd00 → 26030701 <span class="font-sans text-xs">('.__('resets daily').')</span></li>
                                                    </ul>
                                                </div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 pt-2">'.__('The sequence resets to 1 automatically when the date period changes.').'</p>
                                            </div>
                                        '))
                                        ->modalSubmitAction(false)
                                        ->modalCancelActionLabel(__('Close'))
                                ),
                            Forms\Components\Placeholder::make('format_preview')
                                ->label(__('Preview'))
                                ->content(function (Forms\Get $get): string {
                                    $format = $get(CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value);
                                    if (empty($format)) {
                                        return '—';
                                    }

                                    return CompanySettingsEnum::applyFormat($format, 1);
                                }),
                        ]),

                ]),

            ]);
    }
}
