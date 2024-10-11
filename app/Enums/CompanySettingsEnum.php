<?php

namespace App\Enums;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

enum CompanySettingsEnum: string
{
    case CLIENT_PREFER_TRADENAME = 'clients.prefer_tradename';
    case VENDOR_PREFER_TRADENAME = 'vendors.prefer_tradename';
    case TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS = 'tasks.fill_actual_start_date_when_in_progress';
    case TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED = 'tasks.fill_actual_end_date_when_closed';
    case FINANCE_CURRENCY = 'finance.currency';
    case LOCALIZATION_LOCALE = 'localization.locale';
    case LOCALIZATION_TIMEZONE = 'localization.timezone';
    case LOCALIZATION_DATE_FORMAT = 'localization.date_format';
    case LOCALIZATION_DATE_TIME_FORMAT = 'localization.date_time_format';

    case DOCS_INVOICE_NUMBER_LAST = 'docs.invoice_number_last';
    case DOCS_INVOICE_NUMBER_FORMAT = 'docs.invoice_number_format';

    public static function getLocales(): array
    {
        return [
            'en' => 'English',
            'es' => 'Español',
            'pt_BR' => 'Português (Brasil)',
        ];
    }

    public static function getCurrencies(): array
    {
        return [

        ];
    }
    public static function getTimezones(): array
    {
        $regions = [
            'Africa' => \DateTimeZone::AFRICA,
            'America' => \DateTimeZone::AMERICA,
            'Antarctica' => \DateTimeZone::ANTARCTICA,
            'Aisa' => \DateTimeZone::ASIA,
            'Atlantic' => \DateTimeZone::ATLANTIC,
            'Europe' => \DateTimeZone::EUROPE,
            'Indian' => \DateTimeZone::INDIAN,
            'Pacific' => \DateTimeZone::PACIFIC,
        ];

        $timezones = [];
        foreach ($regions as $name => $mask) {
            $zones = \DateTimeZone::listIdentifiers($mask);
            foreach ($zones as $timezone) {
                // Lets sample the time there right now
                $time = new \DateTime(null, new \DateTimeZone($timezone));
                $utcTime = new \DateTime(null, new \DateTimeZone('UTC'));

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

    public static function getDateFormats(): array
    {
        return [
            'd/m/Y' => 'd/m/Y',
            'm/d/Y' => 'm/d/Y',
            'Y/m/d' => 'Y/m/d',
            'd-m-Y' => 'd-m-Y',
            'm-d-Y' => 'm-d-Y',
            'Y-m-d' => 'Y-m-d',
            'd.m.Y' => 'd.m.Y',
            'm.d.Y' => 'm.d.Y',
            'Y.m.d' => 'Y.m.d',
        ];
    }

    public static function getDateTimeFormats(): array
    {
        return [
            'd/m/Y H:i' => 'd/m/Y H:i',
            'd/m/Y H:i:s' => 'd/m/Y H:i:s',
            'm/d/Y h:i' => 'm/d/Y h:i',
            'm/d/Y h:i:s' => 'm/d/Y h:i:s',
            'm/d/Y h:i A' => 'm/d/Y h:i A',
            'Y/m/d H:i' => 'Y/m/d H:i',
            'Y/m/d H:i:s' => 'Y/m/d H:i:s',
            'd-m-Y H:i' => 'd-m-Y H:i',
            'd-m-Y H:i:s' => 'd-m-Y H:i:s',
            'm-d-Y h:i' => 'm-d-Y h:i',
            'm-d-Y h:i:s' => 'm-d-Y h:i:s',
            'm-d-Y h:i A' => 'm-d-Y h:i A',
            'Y-m-d H:i' => 'Y-m-d H:i',
            'Y-m-d H:i:s' => 'Y-m-d H:i:s',
            'd.m.Y H:i' => 'd.m.Y H:i',
            'd.m.Y H:i:s' => 'd.m.Y H:i:s',
            'm.d.Y h:i' => 'm.d.Y h:i',
            'm.d.Y h:i:s' => 'm.d.Y h:i:s',
            'm.d.Y h:i A' => 'm.d.Y h:i A',
            'Y.m.d H:i' => 'Y.m.d H:i',
            'Y.m.d H:i:s' => 'Y.m.d H:i:s',
        ];
    }

    public function getNextDocNumber(): int
    {
        $allowed = [
            self::DOCS_INVOICE_NUMBER_LAST,
        ];

        if (in_array($this, $allowed) === false) {
            throw new \Exception($this . ' setting is not allowed to get the next number');
        }

        $number = auth()->user()->company->settings()
            ->get(
                $this->value,
                0
            );

        $number++;

        auth()->user()->company->settings()
            ->set(
                $this->value,
                $number
            );


        return $number;
    }

    private function formatDocNumer(string $number): string
    {
        $result = 'ym000';

        $setting = str_replace('LAST', 'FORMAT', $this->name);

        if (auth()->user()) {
            $result = auth()->user()->company->settings()
                ->get(
                    $setting,
                    'ym000'
                );
        }

        $zeros = Str::substrCount($result, '0');

        $datePatterns = ['y', 'Y', 'm', 'M', 'd'];

        foreach ($datePatterns as $pattern) {
            if (Str::contains($result, $pattern)) {
                $replace = Carbon::now()->format($pattern);
                $result = str_replace($pattern, $replace, $result);
            }
        }

        $formatedNumber = str_pad($number, $zeros, '0', STR_PAD_LEFT);

        $result = str_replace(
            str_pad('', $zeros, '0', STR_PAD_LEFT),
            $formatedNumber,
            $result
        );

        return $result;
    }

    public function getNextDocNumberFormated()
    {
        $number = $this->getNextDocNumber();

        return $this->formatDocNumer($number);
    }
}
