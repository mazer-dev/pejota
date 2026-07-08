<?php

namespace Tests\Feature\Mail;

use App\Filament\App\Pages\CompanyMailSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class CompanyMailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Landlord::addTenant('company_id', $this->user->company->id);
    }

    public function test_page_loads(): void
    {
        $this->get(CompanyMailSettings::getUrl())->assertOk();
    }

    public function test_save_creates_config_for_company(): void
    {
        Livewire::test(CompanyMailSettings::class)
            ->fillForm([
                'driver' => 'smtp',
                'host' => 'smtp.example.test',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user@example.test',
                'password' => 'topsecret',
                'from_address' => 'me@example.test',
                'from_name' => 'Me',
                'reply_to' => 'reply@example.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $config = $this->user->company->fresh()->mailConfig;
        $this->assertNotNull($config);
        $this->assertSame('smtp.example.test', $config->host);
        $this->assertSame('topsecret', $config->password);
    }

    public function test_saving_with_blank_password_preserves_existing(): void
    {
        $this->user->company->mailConfig()->create([
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.test',
            'password' => 'keepme',
            'from_address' => 'me@example.test',
        ]);

        Livewire::test(CompanyMailSettings::class)
            ->fillForm([
                'host' => 'smtp.changed.test',
                'port' => 587,
                'username' => 'user@example.test',
                'password' => '', // left blank -> must not wipe the stored secret
                'from_address' => 'me@example.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $config = $this->user->company->fresh()->mailConfig;
        $this->assertSame('smtp.changed.test', $config->host);
        $this->assertSame('keepme', $config->password);
    }

    public function test_password_is_not_prefilled_when_mounting_with_existing_config(): void
    {
        $this->user->company->mailConfig()->create([
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.test',
            'password' => 'storedsecret',
            'from_address' => 'me@example.test',
        ]);

        Livewire::test(CompanyMailSettings::class)
            ->assertSet('data.password', null);
    }

    public function test_shows_gmail_help_action(): void
    {
        Livewire::test(CompanyMailSettings::class)
            ->assertActionExists('gmail-smtp')
            ->mountAction('gmail-smtp')
            ->assertSee('smtp.gmail.com');
    }
}
