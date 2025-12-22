<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    //we should add use HasFactory; here later if we need to use factories!!!!

    /**
     * The attributes that are mass assignable.
     * This allows these fields to be saved to the database.
     */
    protected $fillable = [
        'name',
        'address',
        'contact',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     * This ensures 'is_active' is treated as a true/false boolean in your code.
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // RELATIONSHIPS (We will add these later)
    // A Branch hasMany Users
    // A Branch hasMany Projects
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

}
