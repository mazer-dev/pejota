<?php

namespace Tests\Feature;

use App\Enums\CompanySettingsEnum;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class CompanySettingsEnumTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
        $this->assertNotNull($this->company, 'Company was not created for test user');
    }

    public function test_first_invoice_number_starts_at_1(): void
    {
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        $number = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $this->assertSame(1, $number);
    }

    public function test_sequential_numbers_increment_within_same_period(): void
    {
        $this->company->settings()->set(
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
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        // Simulate being in a previous period
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value,
            5
        );
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value,
            '9901' // January 1999 — definitely not the current period
        );

        $number = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $this->assertSame(1, $number);
    }

    public function test_counter_does_not_reset_within_current_period(): void
    {
        $currentPeriod = Carbon::now()->format('ym');

        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value,
            7
        );
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value,
            $currentPeriod
        );

        $number = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $this->assertSame(8, $number);
    }

    public function test_peek_does_not_mutate_and_is_repeatable(): void
    {
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        $first = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->peekNextDocNumber();
        $second = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->peekNextDocNumber();

        $this->assertSame(1, $first);
        $this->assertSame(1, $second);

        $stored = $this->company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value
        );

        $this->assertNull($stored, 'Peeking must not persist the counter');
    }

    public function test_peek_then_consume_returns_same_number(): void
    {
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );

        $peeked = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->peekNextDocNumber();
        $consumed = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $this->assertSame(1, $peeked);
        $this->assertSame(1, $consumed);
        $this->assertSame(2, CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->peekNextDocNumber());
    }

    public function test_period_is_saved_after_reset(): void
    {
        $expectedPeriod = Carbon::now()->format('ym');

        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_FORMAT->value,
            'ym000'
        );
        $this->company->settings()->set(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value,
            '9901'
        );

        CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumber();

        $savedPeriod = $this->company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST_PERIOD->value
        );

        $this->assertSame($expectedPeriod, $savedPeriod);

        $savedNumber = $this->company->settings()->get(
            CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->value
        );

        $this->assertSame(1, $savedNumber);
    }
}
