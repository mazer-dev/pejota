<?php

namespace App\Services\Mail;

use App\Enums\MailEncryptionEnum;
use App\Models\CompanyMailConfig;

class CompanyMailerFactory
{
    public function build(CompanyMailConfig $config): string
    {
        config(['mail.mailers.company' => [
            'transport' => 'smtp',
            'host' => $config->host,
            'port' => $config->port,
            'scheme' => $config->encryption === MailEncryptionEnum::Ssl ? 'smtps' : null,
            'username' => $config->username,
            'password' => $config->password,
            'timeout' => null,
        ]]);

        return 'company';
    }
}
