<?php

namespace Tests\Feature\Mail;

use App\Models\CompanyMailConfig;
use App\Services\Mail\CompanyMailerFactory;
use Tests\TestCase;

class CompanyMailerFactoryTest extends TestCase
{
    public function test_build_registers_company_mailer_from_config_and_returns_name(): void
    {
        $config = new CompanyMailConfig([
            'driver' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.test',
            'password' => 's3cret',
            'from_address' => 'me@example.test',
        ]);

        $name = app(CompanyMailerFactory::class)->build($config);

        $this->assertSame('company', $name);
        $this->assertSame('smtp', config('mail.mailers.company.transport'));
        $this->assertSame('smtp.example.test', config('mail.mailers.company.host'));
        $this->assertSame(587, config('mail.mailers.company.port'));
        $this->assertNull(config('mail.mailers.company.scheme'));
        $this->assertSame('user@example.test', config('mail.mailers.company.username'));
        $this->assertSame('s3cret', config('mail.mailers.company.password'));
    }

    public function test_build_scheme_is_null_when_not_ssl(): void
    {
        $config = new CompanyMailConfig([
            'host' => 'smtp.example.test',
            'port' => 25,
            'username' => 'u',
            'password' => 'p',
            'from_address' => 'me@example.test',
        ]);

        app(CompanyMailerFactory::class)->build($config);

        $this->assertNull(config('mail.mailers.company.scheme'));
    }

    public function test_build_maps_ssl_to_smtps(): void
    {
        $config = new CompanyMailConfig([
            'host' => 'smtp.example.test',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'u',
            'password' => 'p',
            'from_address' => 'me@example.test',
        ]);

        app(CompanyMailerFactory::class)->build($config);

        $this->assertSame('smtps', config('mail.mailers.company.scheme'));
    }
}
