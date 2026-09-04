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
            ['priority', 'client', 'title', 'done_today'],
            $user->fresh()->settings()->get(self::KEY),
        );
    }

    public function test_does_not_duplicate_when_both_keys_are_stored(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(self::KEY, ['client.labelName', 'title', 'client']);

        (new NormalizeTaskListColumnSettings)();

        $this->assertSame(
            ['client', 'title', 'done_today'],
            $user->fresh()->settings()->get(self::KEY),
        );
    }

    public function test_adds_done_today_to_an_already_stored_preference(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(self::KEY, ['title']);

        $this->assertSame(1, (new NormalizeTaskListColumnSettings)());

        $this->assertSame(['title', 'done_today'], $user->fresh()->settings()->get(self::KEY));
    }

    public function test_does_not_create_a_preference_for_a_user_who_has_none(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, (new NormalizeTaskListColumnSettings)());

        $this->assertNull($user->fresh()->settings()->get(self::KEY));
    }

    public function test_leaves_an_already_normalized_setting_untouched(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(self::KEY, ['priority', 'client', 'done_today']);

        $this->assertSame(0, (new NormalizeTaskListColumnSettings)());

        $this->assertSame(['priority', 'client', 'done_today'], $user->fresh()->settings()->get(self::KEY));
    }

    public function test_is_idempotent(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(self::KEY, ['client.labelName']);

        (new NormalizeTaskListColumnSettings)();
        $this->assertSame(0, (new NormalizeTaskListColumnSettings)());

        $this->assertSame(['client', 'done_today'], $user->fresh()->settings()->get(self::KEY));
    }

    public function test_preserves_sibling_settings(): void
    {
        $user = User::factory()->create();
        $user->settings()->set(UserSettingsEnum::LOCALIZATION_LOCALE->value, 'pt_BR');
        $user->settings()->set(self::KEY, ['client.labelName']);

        (new NormalizeTaskListColumnSettings)();

        $fresh = $user->fresh();
        $this->assertSame('pt_BR', $fresh->settings()->get(UserSettingsEnum::LOCALIZATION_LOCALE->value));
        $this->assertSame(['client', 'done_today'], $fresh->settings()->get(self::KEY));
    }
}
