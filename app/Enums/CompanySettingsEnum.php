<?php

namespace App\Enums;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

enum CompanySettingsEnum: string
{
    // TODO this should be a service
    case CLIENT_PREFER_TRADENAME = 'clients.prefer_tradename';
    case VENDOR_PREFER_TRADENAME = 'vendors.prefer_tradename';
    case TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS = 'tasks.fill_actual_start_date_when_in_progress';
    case TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED = 'tasks.fill_actual_end_date_when_closed';
    case TASKS_DEFAULT_LIST_COLUMNS = 'tasks.default_list_columns';
    case FINANCE_CURRENCY = 'finance.currency';
    case LOCALIZATION_LOCALE = 'localization.locale';
    case LOCALIZATION_TIMEZONE = 'localization.timezone';
    case LOCALIZATION_DATE_FORMAT = 'localization.date_format';
    case LOCALIZATION_DATE_TIME_FORMAT = 'localization.date_time_format';
    case DOCS_INVOICE_NUMBER_LAST = 'docs.invoice_number_last';
    case DOCS_INVOICE_NUMBER_FORMAT = 'docs.invoice_number_format';
    case DOCS_INVOICE_NUMBER_LAST_PERIOD = 'docs.invoice_number_last_period';

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
                $ampm = $time->format('H') > 12 ? ' ('.$time->format('g:i a').')' : '';

                $time_offset = $time->getOffset() / 3600;
                $utc_offset = $utcTime->getOffset() / 3600;

                // Remove region name and add a sample time
                $timezones[$name][$timezone] =
                    substr($timezone, strlen($name) + 1).' - '.
                    $time->format('H:i').$ampm.
                    ' ('.$time_offset - $utc_offset.'h) ';
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

    /**
     * Returns the next sequential document number, resetting to 1 when
     * the date period encoded in the configured format mask changes.
     *
     * Side effects: updates docs.invoice_number_last and (on period change)
     * docs.invoice_number_last_period in company settings.
     */
    public function getNextDocNumber(): int
    {
        $allowed = [
            self::DOCS_INVOICE_NUMBER_LAST,
        ];

        if (in_array($this, $allowed) === false) {
            throw new \Exception($this.' setting is not allowed to get the next number');
        }

        $company = auth()->user()->company;

        $format = $company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        ) ?? 'ym000';

        $currentPeriod = $this->getCurrentPeriod($format);

        $storedPeriod = $company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value
        );

        if ($storedPeriod !== $currentPeriod) {
            $number = 1;

            $company->settings()->set($this->value, $number);

            $company->settings()->set(
                CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value,
                $currentPeriod
            );
        } else {
            $number = $company->settings()->get($this->value, 0);
            $number++;

            $company->settings()->set($this->value, $number);
        }

        return $number;
    }

    /**
     * Renders a format mask into a document number string.
     * Replaces recognised date tokens with current date values and
     * zero-padding sequences with the left-padded sequential number.
     */
    public static function applyFormat(string $format, int $number): string
    {
        $datePatterns = ['y', 'Y', 'm', 'M', 'd'];
        $zeros = Str::substrCount($format, '0');

        $result = '';
        foreach (str_split($format) as $char) {
            if (in_array($char, $datePatterns)) {
                $result .= Carbon::now()->format($char);
            } else {
                $result .= $char;
            }
        }

        $formatedNumber = str_pad((string) $number, $zeros, '0', STR_PAD_LEFT);

        return str_replace(
            str_pad('', $zeros, '0', STR_PAD_LEFT),
            $formatedNumber,
            $result
        );
    }

    private function formatDocNumer(string $number): string
    {
        $format = 'ym000';

        if (auth()->user()) {
            $format = auth()->user()->company->settings()
                ->get(
                    CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
                    'ym000'
                ) ?? 'ym000';
        }

        return self::applyFormat($format, (int) $number);
    }

    /**
     * Derives the current period string from the format mask by extracting
     * and formatting only the recognised date tokens (y, Y, m, M, d).
     *
     * Examples:
     * - 'ym000'  → extracts 'y', 'm'  → '2604' (monthly)
     * - 'Y000'   → extracts 'Y'       → '2026' (yearly)
     * - 'Y-m-000'→ extracts 'Y', 'm'  → '202603' (monthly, safe with separators)
     */
    private function getCurrentPeriod(string $format): string
    {
        $datePatterns = ['y', 'Y', 'm', 'M', 'd'];
        $period = '';

        foreach (str_split($format) as $char) {
            if (in_array($char, $datePatterns)) {
                $period .= Carbon::now()->format($char);
            }
        }

        return $period;
    }

    public function getNextDocNumberFormated(): string
    {
        $number = $this->getNextDocNumber();

        return $this->formatDocNumer($number);
    }
}
