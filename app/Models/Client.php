<?php

namespace App\Models;

use App\Enums\CompanySettingsEnum;
use Illuminate\Support\Facades\Auth; // Added for Auth facade support
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;

class Client extends Model
{
    use BelongsToTenants,
        HasFactory,
        HasFilamentComments;

    protected $guarded = ['id'];

    /**
     * Determine whether to show the Tradename or Legal Name based on Company Settings.
     */
    public function labelName(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                // Safety check: if user is not logged in, return the name to prevent errors
                if (!Auth::check()) {
                    return $this->name;
                }

                // Use the Auth facade to resolve the "user()" method error
                $preferTradename = Auth::user()->company
                    ->settings()
                    ->get(CompanySettingsEnum::CLIENT_PREFER_TRADENAME->value);

                return $preferTradename ? $this->tradename : $this->name;
            },
        );
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}