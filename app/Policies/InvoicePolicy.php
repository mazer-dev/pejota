<?php

namespace App\Policies;

use App\Enums\CompanyRoleEnum;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /** @return array<int, string> */
    private function managers(): array
    {
        return [CompanyRoleEnum::Owner->value, CompanyRoleEnum::Admin->value];
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole($this->managers());
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasRole($this->managers());
    }

    public function create(User $user): bool
    {
        return $user->hasRole($this->managers());
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasRole($this->managers());
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole($this->managers());
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole($this->managers());
    }
}
