<?php

namespace App\Services\Assistant;

/**
 * Converts the assistant's markdown answers into WhatsApp-friendly text.
 * WhatsApp has no headings, links or tables: `# título` becomes `*título*`,
 * `**bold**` becomes `*bold*`, `[text](url)` becomes `text (url)`, markdown
 * tables collapse into one line per row with ` — ` between cells. Lists and
 * fenced code blocks pass through untouched, and runs of 3+ newlines
 * collapse to a single blank line.
 */
class WhatsappMarkdownConverter
{
    public function toWhatsapp(string $markdown): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $output = [];
        $insideFence = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*```/', $line)) {
                $insideFence = ! $insideFence;
                $output[] = $line;

                continue;
            }

            if ($insideFence) {
                $output[] = $line;

                continue;
            }

            $output[] = $this->convertLine($line);
        }

        $text = implode("\n", array_filter($output, fn (?string $line): bool => $line !== null));

        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /**
     * Splits the text into chunks of at most $maxChars, preferring paragraph
     * boundaries, then line boundaries, then a hard cut.
     *
     * @return array<int, string>
     */
    public function chunk(string $text, int $maxChars = 4000): array
    {
        $text = trim($text);
        $maxChars = max(1, $maxChars);

        if ($text === '') {
            return [];
        }

        $chunks = [];

        while (mb_strlen($text) > $maxChars) {
            $slice = mb_substr($text, 0, $maxChars);

            $cut = mb_strrpos($slice, "\n\n");
            if ($cut === false || $cut < (int) ($maxChars * 0.3)) {
                $cut = mb_strrpos($slice, "\n");
            }
            if ($cut === false || $cut < (int) ($maxChars * 0.3)) {
                $cut = mb_strrpos($slice, ' ');
            }
            if ($cut === false || $cut === 0) {
                $cut = $maxChars;
            }

            $chunks[] = trim(mb_substr($text, 0, $cut));
            $text = trim(mb_substr($text, $cut));
        }

        if ($text !== '') {
            $chunks[] = $text;
        }

        return $chunks;
    }

    /**
     * Returns null for table separator rows (|---|---|) so they disappear.
     */
    private function convertLine(string $line): ?string
    {
        if (preg_match('/^\s*\|(\s*:?-+:?\s*\|)+\s*$/', $line)) {
            return null;
        }

        if (preg_match('/^\s*\|.*\|\s*$/', $line)) {
            $cells = array_map('trim', explode('|', trim(trim($line), '|')));

            $line = implode(' — ', array_filter($cells, fn (string $cell): bool => $cell !== ''));
        }

        $line = (string) preg_replace('/^\s*#{1,6}\s+(.+?)\s*$/', '*$1*', $line);

        $line = (string) preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $line);

        $line = (string) preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1 ($2)', $line);

        return $line;
    }
}
