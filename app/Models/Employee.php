<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    /**
     * The attributes that are mass assignable.
     * These match the fields we defined in our migration and form.
     */
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'job_title',
        'salary',
        'hire_date',
        'status',
    ];
}
