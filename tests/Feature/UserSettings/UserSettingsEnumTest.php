<?php

namespace Tests\Feature\UserSettings;

use App\Enums\UserSettingsEnum;
use Tests\TestCase;

class UserSettingsEnumTest extends TestCase
{
    public function test_case_values_are_stable(): void
    {
        $this->assertSame('localization.locale', UserSettingsEnum::LOCALIZATION_LOCALE->value);
        $this->assertSame('localization.timezone', UserSettingsEnum::LOCALIZATION_TIMEZONE->value);
        $this->assertSame('localization.date_format', UserSettingsEnum::LOCALIZATION_DATE_FORMAT->value);
        $this->assertSame('localization.date_time_format', UserSettingsEnum::LOCALIZATION_DATE_TIME_FORMAT->value);
        $this->assertSame('tasks.default_list_columns', UserSettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value);
    }

    public function test_option_helpers_return_expected_keys(): void
    {
        $this->assertArrayHasKey('pt_BR', UserSettingsEnum::getLocales());
        $this->assertArrayHasKey('Y-m-d', UserSettingsEnum::getDateFormats());
        $this->assertArrayHasKey('Y-m-d H:i:s', UserSettingsEnum::getDateTimeFormats());
        $this->assertNotEmpty(UserSettingsEnum::getTimezones());
    }
}
