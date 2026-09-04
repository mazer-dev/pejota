<?php

namespace Tests\Feature\UserSettings;

use App\Enums\UserSettingsEnum;
use App\Models\User;
use App\Services\NormalizeTaskListColumnSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizeTaskListColumnSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = UserSettingsEnum::TASKS_DEFAULT_LIST_COLUMNS->value;

    public function test_renames_the_legacy_client_key_keeping_the_position(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(self::KEY, ['priority', 'client.labelName', 'title']);

        $this->assertSame(1, (new NormalizeTaskListColumnSettings)());

        $this->assertSame(
            ['priority', 'client', 'title'],
            $user->fresh()->settings()->get(self::KEY),
        );
    }

    public function test_does_not_duplicate_when_both_keys_are_stored(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(self::KEY, ['client.labelName', 'title', 'client']);

        (new NormalizeTaskListColumnSettings)();

        $this->assertSame(
            ['client', 'title'],
            $user->fresh()->settings()->get(self::KEY),
        );
    }

    public function test_leaves_a_setting_without_the_legacy_key_untouched(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(self::KEY, ['priority', 'client']);

        $this->assertSame(0, (new NormalizeTaskListColumnSettings)());

        $this->assertSame(['priority', 'client'], $user->fresh()->settings()->get(self::KEY));
    }

    public function test_is_idempotent(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(self::KEY, ['client.labelName']);

        (new NormalizeTaskListColumnSettings)();
        $this->assertSame(0, (new NormalizeTaskListColumnSettings)());

        $this->assertSame(['client'], $user->fresh()->settings()->get(self::KEY));
    }

    public function test_preserves_sibling_settings(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');
        $user->settings()->set(self::KEY, ['client.labelName']);

        (new NormalizeTaskListColumnSettings)();

        $fresh = $user->fresh();
        $this->assertSame('pt_BR', $fresh->settings()->get(UserSettingsEnum::LOCALIZATION_LOCALE->value));
        $this->assertSame(['client'], $fresh->settings()->get(self::KEY));
    }
}
