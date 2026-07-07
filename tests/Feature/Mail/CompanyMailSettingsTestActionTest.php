<?php

namespace Tests\Feature\Mail;

use App\Filament\App\Pages\CompanyMailSettings;
use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\TestCase;

class CompanyMailSettingsTestActionTest extends TestCase
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

    public function test_send_test_email_dispatches_test_mail_to_recipient(): void
    {
        Mail::fake();

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
            ])
            ->callAction('sendTest', ['test_recipient' => 'target@example.test'])
            ->assertHasNoActionErrors();

        Mail::assertSent(TestMail::class, function (TestMail $mail): bool {
            return $mail->hasTo('target@example.test');
        });
    }

    public function test_send_test_email_uses_stored_password_when_form_password_blank(): void
    {
        $this->user->company->mailConfig()->create([
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.test',
            'password' => 'storedsecret',
            'from_address' => 'me@example.test',
        ]);

        Mail::fake();

        Livewire::test(CompanyMailSettings::class)
            ->callAction('sendTest', ['test_recipient' => 'target@example.test'])
            ->assertHasNoActionErrors();

        Mail::assertSent(TestMail::class);
    }

    public function test_send_test_email_failure_is_caught_and_notified(): void
    {
        Mail::shouldReceive('mailer')->andThrow(new \RuntimeException('smtp down'));

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
            ])
            ->callAction('sendTest', ['test_recipient' => 'target@example.test'])
            ->assertHasNoActionErrors()
            ->assertNotified(__('Test email failed'));
    }
}
