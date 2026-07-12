<?php

namespace Tests\Feature\UserSettings;

use App\Enums\CompanySettingsEnum;
use Tests\TestCase;

class CompanySettingsEnumCleanupTest extends TestCase
{
    public function test_moved_cases_are_gone_from_company_settings_enum(): void
    {
        $names = array_map(
            static fn (CompanySettingsEnum $case): string => $case->name,
            CompanySettingsEnum::cases(),
        );

        $this->assertNotContains('LOCALIZATION_LOCALE', $names);
        $this->assertNotContains('LOCALIZATION_TIMEZONE', $names);
        $this->assertNotContains('LOCALIZATION_DATE_FORMAT', $names);
        $this->assertNotContains('LOCALIZATION_DATE_TIME_FORMAT', $names);
        $this->assertNotContains('TASKS_DEFAULT_LIST_COLUMNS', $names);

        $this->assertFalse(method_exists(CompanySettingsEnum::class, 'getLocales'));
        $this->assertFalse(method_exists(CompanySettingsEnum::class, 'getTimezones'));
        $this->assertFalse(method_exists(CompanySettingsEnum::class, 'getDateFormats'));
        $this->assertFalse(method_exists(CompanySettingsEnum::class, 'getDateTimeFormats'));

        // Chaves que PERMANECEM por-empresa continuam presentes.
        $this->assertContains('FINANCE_CURRENCY', $names);
        $this->assertContains('TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS', $names);
    }
}
