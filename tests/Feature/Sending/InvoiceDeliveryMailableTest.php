<?php

namespace Tests\Feature\Sending;

use App\Mail\InvoiceDeliveryMailable;
use App\Models\InvoiceDelivery;
use Tests\TestCase;

class InvoiceDeliveryMailableTest extends TestCase
{
    private function delivery(): InvoiceDelivery
    {
        return new InvoiceDelivery([
            'to' => ['a@acme.test'],
            'cc' => ['c@acme.test'],
            'subject' => "Invoice INV-1\r\nBcc: evil@x.test",
            'body' => '<p>Hello</p>',
            'signature' => '<p>Regards, Me</p>',
        ]);
    }

    public function test_subject_is_sanitized_and_content_combines_body_and_signature(): void
    {
        $mail = new InvoiceDeliveryMailable($this->delivery(), [], 'me@x.test', 'Me', 'reply@x.test');

        $envelope = $mail->envelope();
        $this->assertStringNotContainsString("\r", $envelope->subject);
        $this->assertStringNotContainsString("\n", $envelope->subject);
        $this->assertSame('Invoice INV-1 Bcc: evil@x.test', $envelope->subject);

        $rendered = $mail->render();
        $this->assertStringContainsString('Hello', $rendered);
        $this->assertStringContainsString('Regards, Me', $rendered);
    }

    public function test_recipients_and_from_are_set(): void
    {
        $mail = new InvoiceDeliveryMailable($this->delivery(), [], 'me@x.test', 'Me', 'reply@x.test');
        $mail->assertHasTo('a@acme.test');
        $mail->assertHasCc('c@acme.test');
        $mail->assertFrom('me@x.test');
        $mail->assertHasReplyTo('reply@x.test');
    }
}
