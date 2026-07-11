<?php

namespace App\Billing;

use App\Contracts\SubscriptionGate;
use App\Models\Company;

class NullBilling implements SubscriptionGate
{
    public function allows(Company $company): bool
    {
        return true;
    }
}
