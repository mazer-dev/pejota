<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NunoMazer\Samehouse\BelongsToTenants;
use Spatie\Tags\HasTags;

class Project extends Model
{
    use BelongsToTenants,
        HasFactory,
        HasTags;

    protected $guarded = ['id'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopeByClient(Builder $query, Client|int|null $client): void
    {
        if ($client) {
            $query->where('client_id', $client);
        }
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public static function activeCount(): int
    {
        return static::where('active', true)->count();
    }

    protected function casts(): array
    {
        return [
            'hourly_rate' => MoneyCast::class,
        ];
    }
}
