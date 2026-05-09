<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
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

    #[Scope]
    protected function byClient(Builder $query, Client|int|null $client)
    {
        if ($client) {
            $query->where('client_id', $client);
        }
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
