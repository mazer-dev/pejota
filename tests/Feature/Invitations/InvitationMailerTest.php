<?php

namespace Tests\Feature\Invitations;

use App\Enums\CompanyRoleEnum;
use App\Mail\InvitationMailable;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Mail\InvitationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class InvitationMailerTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_it_sends_the_invitation_to_the_invitee_via_default_mailer(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $company = $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
        $invitation = Invitation::create([
            'company_id' => $company->id, 'email' => 'invitee@x.com', 'role' => CompanyRoleEnum::Member,
            'token' => 'tok-mail', 'expires_at' => now()->addDay(), 'invited_by' => $owner->id,
        ]);

        app(InvitationMailer::class)->send($invitation);

        Mail::assertSent(InvitationMailable::class, function (InvitationMailable $mail): bool {
            return $mail->hasTo('invitee@x.com');
        });
    }

    public function test_it_sends_the_invitation_via_the_company_mailer_when_config_is_complete(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $company = $this->actingInCompany($owner);

        $config = $company->mailConfig()->create([
            'driver' => 'smtp',
            'host' => 'smtp.company-mailer.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@company-mailer.test',
            'password' => 's3cret',
            'from_address' => 'no-reply@company-mailer.test',
            'from_name' => 'Company Mailer',
        ]);

        $invitation = Invitation::create([
            'company_id' => $company->id, 'email' => 'invitee@x.com', 'role' => CompanyRoleEnum::Member,
            'token' => 'tok-company-mail', 'expires_at' => now()->addDay(), 'invited_by' => $owner->id,
        ]);

        app(InvitationMailer::class)->send($invitation);

        $this->assertSame('smtp.company-mailer.test', config('mail.mailers.company.host'));

        Mail::assertSent(InvitationMailable::class, function (InvitationMailable $mail) use ($config): bool {
            return $mail->hasTo('invitee@x.com')
                && $mail->fromAddress?->address === $config->from_address
                && $mail->fromAddress?->name === $config->from_name;
        });
    }
}
