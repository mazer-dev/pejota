<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function Client(): HasOne {
        return $this->hasOne(Client::class);
    }

    public function Project(): HasOne {
        return $this->hasOne(Client::class);
    }
}
