<?php

namespace App\Models;

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

    protected $fillable = [
        'branch_id', // Added
        'code',
        'name',
        'client_id',
        'start_date',
        'end_date',
        'budget',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
    ];

    // RELATIONSHIPS

    /**
     * Link to the Branch
     */use BelongsToTenants, HasFactory, HasTags;

    // This tells the Tenancy package to track 'branch_id' instead of 'company_id'
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contracts(): HasMany 
    {
        return $this->hasMany(Contract::class);
    }

    // SCOPES
    
    public function scopeByClient(Builder $query, Client|int|null $client)
    {
        if ($client) {
            $query->where('client_id', $client);
        }
    }
    /**
     * Link to Daily Logs
     */
    public function dailyLogs(): HasMany
    {
        return $this->hasMany(DailyLog::class);
    }
    /* Define the relationship between Project and Tasks */
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

      /* Define the relationship between Project and Milestones */
    public function milestones()
    {
    return $this->hasMany(Milestone::class);
    }
    
}