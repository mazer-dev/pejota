<?php

namespace Tests\Feature;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencySelectOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_active_currencies_ordered_with_translated_label(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
        Currency::create(['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'is_active' => true]);
        Currency::create(['code' => 'XXX', 'name' => 'Inactive', 'symbol' => 'x', 'is_active' => false]);

        $options = Currency::selectOptions();

        $this->assertSame(['BRL', 'USD'], array_keys($options));
        $this->assertSame('USD — '.__('US Dollar'), $options['USD']);
        $this->assertArrayNotHasKey('XXX', $options);
    }

    public function test_ensure_injects_missing_code(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);

        $options = Currency::selectOptions('XAU');

        $this->assertArrayHasKey('XAU', $options);
        $this->assertSame('XAU', $options['XAU']);
    }
}
