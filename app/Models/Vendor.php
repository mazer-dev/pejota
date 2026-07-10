<?php

namespace App\Models;

use App\Enums\CompanySettingsEnum;
use App\Helpers\PejotaHelper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;

class Vendor extends Model
{
    use BelongsToTenants,
        HasFactory,
        HasFilamentComments;

    protected $guarded = ['id'];

    public function labelName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => PejotaHelper::currentCompany()
                ->settings()->get(CompanySettingsEnum::VENDOR_PREFER_TRADENAME->value)
                        ? $this->tradename
                        : $this->name,
        );
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
