<?php

namespace Tests\Unit\Assistant;

use App\Services\Assistant\WhatsappMarkdownConverter;
use Tests\TestCase;

class WhatsappMarkdownConverterTest extends TestCase
{
    private WhatsappMarkdownConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = new WhatsappMarkdownConverter;
    }

    public function test_headings_become_bold_lines(): void
    {
        $this->assertSame('*Resumo do dia*', $this->converter->toWhatsapp('## Resumo do dia'));
    }

    public function test_double_asterisk_bold_becomes_single_asterisk(): void
    {
        $this->assertSame(
            'O total é *R$ 1.000,00* hoje.',
            $this->converter->toWhatsapp('O total é **R$ 1.000,00** hoje.'),
        );
    }

    public function test_links_become_text_with_url_in_parentheses(): void
    {
        $this->assertSame(
            'Veja a fatura (https://pejota.test/faturas/9).',
            $this->converter->toWhatsapp('Veja [a fatura](https://pejota.test/faturas/9).'),
        );
    }

    public function test_tables_collapse_into_dash_separated_lines(): void
    {
        $markdown = implode("\n", [
            '| Cliente | Total |',
            '| --- | --- |',
            '| Felipe | R$ 100 |',
        ]);

        $this->assertSame(
            "Cliente — Total\nFelipe — R$ 100",
            $this->converter->toWhatsapp($markdown),
        );
    }

    public function test_lists_and_fenced_code_blocks_pass_through_untouched(): void
    {
        $markdown = implode("\n", [
            '- item um',
            '- item dois',
            '```',
            '## isto não é título',
            '**nem negrito**',
            '```',
        ]);

        $this->assertSame($markdown, $this->converter->toWhatsapp($markdown));
    }

    public function test_three_or_more_newlines_collapse_to_a_blank_line(): void
    {
        $this->assertSame(
            "Primeiro parágrafo.\n\nSegundo parágrafo.",
            $this->converter->toWhatsapp("Primeiro parágrafo.\n\n\n\nSegundo parágrafo."),
        );
    }

    public function test_chunk_splits_on_paragraph_boundaries(): void
    {
        $first = str_repeat('a', 60);
        $second = str_repeat('b', 60);
        $text = $first."\n\n".$second;

        $chunks = $this->converter->chunk($text, 100);

        $this->assertSame([$first, $second], $chunks);
    }

    public function test_chunk_returns_a_single_chunk_when_under_the_limit(): void
    {
        $this->assertSame(['curto'], $this->converter->chunk('curto', 4000));
    }

    public function test_chunk_hard_cuts_text_without_any_boundary(): void
    {
        $text = str_repeat('x', 250);

        $chunks = $this->converter->chunk($text, 100);

        $this->assertCount(3, $chunks);
        $this->assertSame($text, implode('', $chunks));
    }
}
