<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class CompanyService
{
    public function create(User $user): Company
    {
        $company = Company::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $company->users()->attach($user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return $company;
    }
}
