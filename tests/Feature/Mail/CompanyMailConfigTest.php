<?php

namespace Tests\Feature\Mail;

use App\Enums\MailDriverEnum;
use App\Enums\MailEncryptionEnum;
use App\Models\Company;
use App\Models\CompanyMailConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use NunoMazer\Samehouse\Facades\Landlord;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class CompanyMailConfigTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    public function test_company_has_one_mail_config(): void
    {
        $config = $this->company->mailConfig()->create([
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.test',
            'password' => 'plzhide',
            'from_address' => 'me@example.test',
        ]);

        $this->assertInstanceOf(CompanyMailConfig::class, $this->company->fresh()->mailConfig);
        $this->assertSame($config->id, $this->company->fresh()->mailConfig->id);
    }

    public function test_password_is_encrypted_at_rest_and_decrypts_via_cast(): void
    {
        $config = $this->company->mailConfig()->create([
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'user@example.test',
            'password' => 'plzhide',
            'from_address' => 'me@example.test',
        ]);

        // Raw column read (intentional: verify ciphertext at rest, bypassing the cast).
        $raw = DB::table('company_mail_configs')->where('id', $config->id)->value('password');

        $this->assertNotSame('plzhide', $raw);
        $this->assertSame('plzhide', $config->fresh()->password);
    }

    public function test_driver_and_encryption_cast_to_enums(): void
    {
        $config = $this->company->mailConfig()->create([
            'driver' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'u',
            'password' => 'p',
            'from_address' => 'me@example.test',
        ]);

        $config = $config->fresh();
        $this->assertSame(MailDriverEnum::Smtp, $config->driver);
        $this->assertSame(MailEncryptionEnum::Ssl, $config->encryption);
    }

    public function test_mail_config_is_scoped_to_owning_company(): void
    {
        $firstCompany = $this->company;
        $firstCompany->mailConfig()->create([
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.test',
            'password' => 'plzhide',
            'from_address' => 'me@example.test',
        ]);

        $second = User::factory()->create();
        $secondCompany = $second->companies()->firstOrFail();

        // Switch the tenant scope to the second company - it must not see the first company's config.
        Landlord::addTenant('company_id', $secondCompany->id);

        $this->assertNull($secondCompany->fresh()->mailConfig);
        $this->assertSame(0, CompanyMailConfig::query()->count());

        // Switching back proves the row is still there, just scoped to its own company.
        Landlord::addTenant('company_id', $firstCompany->id);
        $this->assertNotNull($firstCompany->fresh()->mailConfig);
    }

    public function test_is_complete_requires_host_port_username_password_and_from_address(): void
    {
        $incomplete = new CompanyMailConfig(['host' => 'smtp.example.test']);
        $this->assertFalse($incomplete->isComplete());

        $complete = new CompanyMailConfig([
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'u',
            'password' => 'p',
            'from_address' => 'me@example.test',
        ]);
        $this->assertTrue($complete->isComplete());
    }
}
