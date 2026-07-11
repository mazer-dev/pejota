<?php

namespace App\Services\Mail;

use App\Mail\InvitationMailable;
use App\Models\Invitation;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

class InvitationMailer
{
    public function __construct(private CompanyMailerFactory $mailerFactory) {}

    public function send(Invitation $invitation): void
    {
        $config = $invitation->company->mailConfig;
        $acceptUrl = route('invitations.accept', ['token' => $invitation->token]);

        if ($config !== null && $config->isComplete()) {
            $mailer = $this->mailerFactory->build($config);
            $from = new Address((string) $config->from_address, $config->from_name);
        } else {
            $mailer = config('mail.default');
            $from = null;
        }

        Mail::mailer($mailer)->send(new InvitationMailable($invitation, $acceptUrl, $from));
    }
}
