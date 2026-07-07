<?php

namespace Tests\Feature\Messaging;

use App\Services\Messaging\TemplateRenderer;
use Tests\TestCase;

class TemplateRendererTest extends TestCase
{
    private function renderer(): TemplateRenderer
    {
        return app(TemplateRenderer::class);
    }

    public function test_replaces_known_tokens(): void
    {
        $out = $this->renderer()->render('Invoice {{ invoice.number }} for {{ client.name }}', [
            'invoice.number' => 'INV-1',
            'client.name' => 'Acme',
        ]);

        $this->assertSame('Invoice INV-1 for Acme', $out);
    }

    public function test_leaves_unknown_tokens_intact(): void
    {
        $out = $this->renderer()->render('Hi {{ unknown.token }}', ['client.name' => 'Acme']);

        $this->assertSame('Hi {{ unknown.token }}', $out);
    }

    public function test_html_mode_escapes_values_not_template(): void
    {
        $out = $this->renderer()->render('<p>{{ client.name }}</p>', ['client.name' => 'A & B'], html: true);

        $this->assertSame('<p>A &amp; B</p>', $out);
    }

    public function test_plain_mode_does_not_escape(): void
    {
        $out = $this->renderer()->render('{{ client.name }}', ['client.name' => 'A & B'], html: false);

        $this->assertSame('A & B', $out);
    }

    public function test_tolerates_spacing_in_tokens(): void
    {
        $out = $this->renderer()->render('{{invoice.number}}/{{  invoice.number  }}', ['invoice.number' => 'X']);

        $this->assertSame('X/X', $out);
    }
}
