<?php

namespace App\Models;

use App\Enums\MailDriverEnum;
use App\Enums\MailEncryptionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

class CompanyMailConfig extends Model
{
    use BelongsToTenants;

    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isComplete(): bool
    {
        return filled($this->host)
            && filled($this->port)
            && filled($this->username)
            && filled($this->password)
            && filled($this->from_address);
    }

    protected function casts(): array
    {
        return [
            'driver' => MailDriverEnum::class,
            'encryption' => MailEncryptionEnum::class,
            'password' => 'encrypted',
            'port' => 'integer',
        ];
    }
}
