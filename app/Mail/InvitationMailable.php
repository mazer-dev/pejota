<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Support\SubjectSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $acceptUrl,
        public ?Address $fromAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress,
            to: [new Address($this->invitation->email)],
            subject: SubjectSanitizer::sanitize(
                __('You have been invited to :company', ['company' => $this->invitation->company->name])
            ),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invitation');
    }
}
