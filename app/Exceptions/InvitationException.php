<?php

namespace App\Exceptions;

use RuntimeException;

class InvitationException extends RuntimeException
{
    public static function notPending(): self
    {
        return new self(__('This invitation is no longer valid.'));
    }

    public static function emailMismatch(): self
    {
        return new self(__('This invitation was issued for a different email address.'));
    }

    public static function alreadyMember(string $email): self
    {
        return new self(__(':email is already a member of this company.', ['email' => $email]));
    }

    public static function lastOwner(): self
    {
        return new self(__('A company must keep at least one owner.'));
    }

    public static function ownerOnly(): self
    {
        return new self(__('Only an owner can manage the owner role.'));
    }
}
