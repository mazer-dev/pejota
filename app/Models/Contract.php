<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'start_at',
        'end_at',
        'signatures',
        'client_id',
        'project_id'
    ];

    public function client(): BelongsTo {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo {
        return $this->belongsTo(Project::class);
    }

    protected function casts(): array
    {
        return [
            'signatures' => 'array',
        ];
    }
}
