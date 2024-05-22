<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CompanyService
{
    public function create(User $user): Company
    {
        return Company::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }
}
