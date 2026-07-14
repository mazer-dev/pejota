<?php

namespace App\Events;

use App\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;

class CompanyCreated
{
    use Dispatchable;

    public function __construct(public Company $company) {}
}
