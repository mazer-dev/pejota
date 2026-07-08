<?php

namespace Tests\Unit\Helpers;

use App\Helpers\PejotaHelper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PejotaHelperOrDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_or_default_getters_fall_back_when_nobody_is_authenticated(): void
    {
        $this->assertGuest();

        $this->assertSame('UTC', PejotaHelper::getUserTimeZoneOrDefault());
        $this->assertSame('Y-m-d', PejotaHelper::getUserDateFormatOrDefault());
        $this->assertSame('en', PejotaHelper::getUserLocateOrDefault());
        $this->assertSame('USD', PejotaHelper::getUserCurrencyOrDefault());
    }

    public function test_or_default_getters_defer_to_the_authenticated_users_company_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertSame(PejotaHelper::getUserDateFormat(), PejotaHelper::getUserDateFormatOrDefault());
        $this->assertSame(PejotaHelper::getUserLocate(), PejotaHelper::getUserLocateOrDefault());
        $this->assertSame(PejotaHelper::getUserCurrency(), PejotaHelper::getUserCurrencyOrDefault());
    }
}
