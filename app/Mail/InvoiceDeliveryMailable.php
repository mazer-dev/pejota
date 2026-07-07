<?php

namespace App\Mail;

use App\Models\InvoiceDelivery;
use App\Support\SubjectSanitizer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceDeliveryMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{data: string, name: string, mime: string}>  $files
     */
    public function __construct(
        public InvoiceDelivery $delivery,
        public array $files = [],
        public string $fromAddress = '',
        public ?string $fromName = null,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress)] : [],
            to: array_map(fn (string $email): Address => new Address($email), $this->delivery->to ?? []),
            cc: array_map(fn (string $email): Address => new Address($email), $this->delivery->cc ?? []),
            subject: SubjectSanitizer::sanitize((string) $this->delivery->subject),
        );
    }

    public function content(): Content
    {
        $html = (string) $this->delivery->body
            .'<br><br>'
            .(string) $this->delivery->signature;

        return new Content(htmlString: $html);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $file): Attachment => Attachment::fromData(fn (): string => $file['data'], $file['name'])
                ->withMime($file['mime']),
            $this->files,
        );
    }
}
