<?php

namespace App\Contracts;

use Filament\Schemas\Components\Component;

/**
 * Schema components appended to a tab of the company settings page.
 *
 * Implementations are registered per tab name in
 * `config('pejota.company_settings_components')`. Open core registers none; the
 * seam exists so an overlay can add its own company-scoped settings to the page
 * users already look at, instead of publishing a settings page of its own.
 */
interface CompanySettingsComponents
{
    /** @return array<int, Component> */
    public static function components(): array;
}
