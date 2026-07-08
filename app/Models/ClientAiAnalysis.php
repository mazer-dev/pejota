<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NunoMazer\Samehouse\BelongsToTenants;

/**
 * A single AI-generated analysis of the relationship with a client.
 * Rows are append-only: a new generation always creates a new row,
 * never overwrites a previous one.
 */
class ClientAiAnalysis extends Model
{
    use BelongsToTenants,
        HasFactory;

    protected $guarded = ['id'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
