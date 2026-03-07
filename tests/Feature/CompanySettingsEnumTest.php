<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompanySettingsEnumTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->assertNotNull($this->user->company, 'Company was not created for test user');
    }

    public function test_first_invoice_number_starts_at_1(): void
    {
        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        $number = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $this->assertSame(1, $number);
    }

    public function test_sequential_numbers_increment_within_same_period(): void
    {
        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        $first = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();
        $second = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();
        $third = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $this->assertSame(1, $first);
        $this->assertSame(2, $second);
        $this->assertSame(3, $third);
    }

    public function test_counter_resets_to_1_when_period_changes(): void
    {
        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        // Simulate being in a previous period
        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value,
            5
        );
        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value,
            '9901' // January 1999 — definitely not the current period
        );

        $number = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $this->assertSame(1, $number);
    }

    public function test_counter_does_not_reset_within_current_period(): void
    {
        $currentPeriod = Carbon::now()->format('ym');

        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );
        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value,
            7
        );
        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value,
            $currentPeriod
        );

        $number = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $this->assertSame(8, $number);
    }

    public function test_period_is_saved_after_reset(): void
    {
        $expectedPeriod = Carbon::now()->format('ym');

        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );
        $this->user->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value,
            '9901'
        );

        CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $savedPeriod = $this->user->company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value
        );

        $this->assertSame($expectedPeriod, $savedPeriod);

        $savedNumber = $this->user->company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value
        );

        $this->assertSame(1, $savedNumber);
    }
}
