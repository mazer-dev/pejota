<?php

namespace App\Services;

use App\Enums\CompanyRoleEnum;
use App\Exceptions\InvitationException;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Mail\InvitationMailer;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class InvitationService
{
    public function invite(Company $company, string $email, CompanyRoleEnum $role, User $invitedBy): Invitation
    {
        $alreadyMember = $company->users()
            ->wherePivotNotNull('joined_at')
            ->where('email', $email)
            ->exists();

        if ($alreadyMember) {
            throw InvitationException::alreadyMember($email);
        }

        $attributes = [
            'role' => $role,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(config('pejota.invitation_expires_after_days')),
            'invited_by' => $invitedBy->id,
            'accepted_at' => null,
        ];

        $invitation = $company->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        if ($invitation !== null) {
            $invitation->update($attributes);
        } else {
            $invitation = $company->invitations()->create([...$attributes, 'email' => $email]);
        }

        app(InvitationMailer::class)->send($invitation);

        return $invitation;
    }

    public function accept(Invitation $invitation, User $user): void
    {
        if (! $invitation->isPending()) {
            throw InvitationException::notPending();
        }

        if (strtolower($invitation->email) !== strtolower($user->email)) {
            throw InvitationException::emailMismatch();
        }

        $company = $invitation->company;

        if (! $company->hasMember($user)) {
            $company->users()->attach($user->id, [
                'invited_at' => $invitation->created_at,
                'joined_at' => now(),
            ]);
        }

        $this->withTeam($company, function () use ($user, $invitation): void {
            $user->unsetRelation('roles');
            $user->syncRoles([$invitation->role->value]);
        });

        $invitation->update(['accepted_at' => now()]);
    }

    public function acceptAsNewUser(Invitation $invitation, string $name, string $password): User
    {
        if (! $invitation->isPending()) {
            throw InvitationException::notPending();
        }

        $user = new User;
        $user->name = $name;
        $user->email = $invitation->email;
        $user->password = $password; // hashed by the model cast
        $user->skipCompanyProvisioning = true;
        $user->save();

        $this->accept($invitation, $user);

        return $user;
    }

    public function changeRole(Company $company, User $member, CompanyRoleEnum $role, User $actor): void
    {
        $touchesOwner = $role === CompanyRoleEnum::Owner || $this->isOwner($company, $member);

        if ($touchesOwner && ! $this->isOwner($company, $actor)) {
            throw InvitationException::ownerOnly();
        }

        if ($role !== CompanyRoleEnum::Owner && $this->isLastOwner($company, $member)) {
            throw InvitationException::lastOwner();
        }

        $this->withTeam($company, function () use ($member, $role): void {
            $member->unsetRelation('roles');
            $member->syncRoles([$role->value]);
        });
    }

    public function removeMember(Company $company, User $member, User $actor): void
    {
        if ($this->isOwner($company, $member) && ! $this->isOwner($company, $actor)) {
            throw InvitationException::ownerOnly();
        }

        if ($this->isLastOwner($company, $member)) {
            throw InvitationException::lastOwner();
        }

        $this->withTeam($company, function () use ($member): void {
            $member->unsetRelation('roles');
            $member->syncRoles([]);
        });

        $company->users()->detach($member->id);
    }

    /**
     * Run a callback with the spatie permissions team set to the company,
     * restoring the previous team-id afterwards (Fase 1 pattern).
     */
    private function withTeam(Company $company, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($company->id);

            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    private function isLastOwner(Company $company, User $member): bool
    {
        return $this->withTeam($company, function () use ($company, $member): bool {
            $member->unsetRelation('roles');

            if (! $member->hasRole(CompanyRoleEnum::Owner->value)) {
                return false;
            }

            $owners = $company->users()
                ->wherePivotNotNull('joined_at')
                ->get()
                ->filter(function (User $user): bool {
                    $user->unsetRelation('roles');

                    return $user->hasRole(CompanyRoleEnum::Owner->value);
                });

            return $owners->count() <= 1;
        });
    }

    private function isOwner(Company $company, User $user): bool
    {
        return $this->withTeam($company, function () use ($user): bool {
            $user->unsetRelation('roles');

            return $user->hasRole(CompanyRoleEnum::Owner->value);
        });
    }
}
