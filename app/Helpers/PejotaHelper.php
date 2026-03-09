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
        return (new MobileDetect)->isMobile();
    }

    /**
     * Get the application version from git (tag + timestamp)
     */
    public static function getVersion(): string
    {
        try {
            $version = trim(shell_exec('git describe --tags 2>/dev/null') ?? '');

            if (! $version) {
                return 'unknown';
            }

            // Remove the hash part (e.g., "-g7fbcb8a")
            $version = preg_replace('/-g[a-f0-9]+$/', '', $version);

            // Get the commit timestamp in YYMMDDhhmm format
            $timestamp = trim(shell_exec('git log -1 --format=%ci 2>/dev/null') ?? '');

            if ($timestamp) {
                // Convert "YYYY-MM-DD HH:MM:SS" to "YYMMDD.hhmm"
                $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', substr($timestamp, 0, 19));
                if ($dateTime) {
                    return $version.' ('.($dateTime->format('ymd').'.'.($dateTime->format('Hi'))).')';
                }
            }

            return $version;
        } catch (\Exception) {
            return 'unknown';
        }
    }
}
