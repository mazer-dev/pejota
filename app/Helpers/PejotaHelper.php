<?php

namespace App\Helpers;

use App\Enums\CompanySettingsEnum;
use Detection\MobileDetect;

class PejotaHelper
{
    /**
     * Format the minutes duration as HH:mm
     */
    public static function formatDuration(?int $duration, bool $formatWithDots = false): string
    {
        if (! $duration) {
            return $formatWithDots ? '00:00' : '00h00';
        }

        return
            str_pad(intdiv($duration, 60), 2, '0', STR_PAD_LEFT)
            .($formatWithDots ? ':' : 'h').
            str_pad($duration % 60, 2, '0', STR_PAD_LEFT);
    }

    public static function getUserTimeZone()
    {
        return auth()->user()->company->settings()->get(CompanySettingsEnum::LOCALIZATION_TIMEZONE->value);
    }

    public static function getUserDateFormat()
    {
        return auth()->user()->company->settings()->get(CompanySettingsEnum::LOCALIZATION_DATE_FORMAT->value) ?? 'Y-m-d';
    }

    public static function getUserDateTimeFormat()
    {
        return auth()->user()->company->settings()->get(CompanySettingsEnum::LOCALIZATION_DATE_TIME_FORMAT->value) ?? 'Y-m-d H:i:s';
    }

    public static function getUserLocate()
    {
        return auth()->user()->company->settings()->get(CompanySettingsEnum::LOCALIZATION_LOCALE->value) ?? 'en';
    }

    public static function getUserCurrency()
    {
        return auth()->user()->company->settings()->get(CompanySettingsEnum::FINANCE_CURRENCY->value) ?? 'USD';
    }

    public static function getUserTaskListDefaultColumns()
    {
        return auth()->user()->company->settings()->get(CompanySettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value) ?? [];
    }

    public static function isMobile(): bool
    {
        return (new MobileDetect())->isMobile();
    }
}
