<?php

namespace App\Contracts;

use App\Models\Company;

interface SubscriptionGate
{
    /**
     * Whether the company's subscription currently permits access.
     */
    public function allows(Company $company): bool;
}
