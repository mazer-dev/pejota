<?php

namespace App\Enums;

use Carbon\Carbon;

enum CompanySettingsEnum: string
{
    case CLIENT_PREFER_TRADENAME = 'clients.prefer_tradename';
    case TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS = 'tasks.fill_actual_start_date_when_in_progress';
    case TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED = 'tasks.fill_actual_end_date_when_closed';
    case FINANCE_CURRENCY = 'finance.currency';
    case LOCALIZATION_LOCALE = 'localization.locale';
    case LOCALIZATION_TIMEZONE = 'localization.timezone';

    public static function getLocales(): array
    {
        return [
            'en' => 'English',
        ];
    }

    public static function getTimezones(): array
    {
        $regions = array(
            'Africa' => \DateTimeZone::AFRICA,
            'America' => \DateTimeZone::AMERICA,
            'Antarctica' => \DateTimeZone::ANTARCTICA,
            'Aisa' => \DateTimeZone::ASIA,
            'Atlantic' => \DateTimeZone::ATLANTIC,
            'Europe' => \DateTimeZone::EUROPE,
            'Indian' => \DateTimeZone::INDIAN,
            'Pacific' => \DateTimeZone::PACIFIC
        );

        $timezones = array();
        foreach ($regions as $name => $mask) {
            $zones = \DateTimeZone::listIdentifiers($mask);
            foreach ($zones as $timezone) {
                // Lets sample the time there right now
                $time = new \DateTime(NULL, new \DateTimeZone($timezone));
                $utcTime = new \DateTime(NULL, new \DateTimeZone('UTC'));

                // Us Americans can't handle millitary time
                $ampm = $time->format('H') > 12 ? ' (' . $time->format('g:i a') . ')' : '';

                $time_offset = $time->getOffset() / 3600;
                $utc_offset = $utcTime->getOffset() / 3600;

                // Remove region name and add a sample time
                $timezones[$name][$timezone] =
                    substr($timezone, strlen($name) + 1) . ' - ' .
                    $time->format('H:i') . $ampm .
                    ' (' . $time_offset - $utc_offset . 'h) ';
            }
        }

        return $timezones;
    }
}
