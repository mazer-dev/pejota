<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $fromAddress;

    public ?string $fromName;

    // Deliberately not named $replyTo: Illuminate\Mail\Mailable already declares an untyped
    // inherited $replyTo property, and re-declaring it with a type here is a PHP fatal error.
    public ?string $replyToAddress;

    public function __construct(string $fromAddress, ?string $fromName = null, ?string $replyTo = null)
    {
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
        $this->replyToAddress = $replyTo;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress)] : [],
            subject: __('PeJota — test email'),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>'.__('This is a test email from PeJota. Your SMTP settings are working correctly.').'</p>',
        );
    }
}
