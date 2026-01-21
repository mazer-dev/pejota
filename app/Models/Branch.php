<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth; // Ensure this is imported
use NunoMazer\Samehouse\BelongsToTenants;

class Branch extends Model
{
    use BelongsToTenants;

    protected $fillable = [
        'name', 'address', 'contact', 'is_active', 'company_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            // Check if a user is logged in to avoid errors during seeding or console commands
            if (Auth::check()) {
                // Directly assign the company_id from the authenticated user
                $model->company_id = Auth::user()->company_id;
            }
        });
    }
}