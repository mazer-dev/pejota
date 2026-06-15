<?php

namespace Tests\Feature;

use Tests\TestCase;

class CurrencyTranslationTest extends TestCase
{
    public function test_currency_names_are_translated_to_pt_br(): void
    {
        app()->setLocale('pt_BR');

        $this->assertSame('Dólar americano', __('US Dollar'));
        $this->assertSame('Real', __('Brazilian Real'));
        $this->assertSame('Dólar canadense', __('Canadian Dollar'));
        $this->assertSame('Libra esterlina', __('Pound Sterling'));
    }

    public function test_currency_names_are_translated_to_es(): void
    {
        app()->setLocale('es');

        $this->assertSame('Dólar estadounidense', __('US Dollar'));
        $this->assertSame('Dólar canadiense', __('Canadian Dollar'));
    }
}
