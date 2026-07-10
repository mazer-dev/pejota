<?php

namespace Tests\Feature\Help;

use App\Filament\App\Pages\CompanySettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvoiceNumberHelpTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingInCompany($user);
    }

    public function test_invoice_number_help_action_shows_markdown_content(): void
    {
        Livewire::test(CompanySettings::class)
            ->assertFormComponentActionExists('docs.invoice_number_format', 'invoice-number')
            ->mountFormComponentAction('docs.invoice_number_format', 'invoice-number')
            ->assertSee('reinicia mensalmente');
    }
}
