<?php

namespace Tests\Feature\UserSettings;

use App\Helpers\PejotaHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_persists_and_reads_a_setting(): void
    {
        $user = User::factory()->create();

        $user->settings()->set('localization.locale', 'pt_BR');

        $this->assertSame('pt_BR', $user->fresh()->settings()->get('localization.locale'));
    }

    public function test_current_user_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->assertNull(PejotaHelper::currentUser());

        $this->actingAs($user);

        $this->assertSame($user->id, PejotaHelper::currentUser()?->id);
    }
}
