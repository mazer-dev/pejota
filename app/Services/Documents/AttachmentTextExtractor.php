<?php

namespace App\Services\Documents;

use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class AttachmentTextExtractor
{
    public function extract(string $filePath, ?string $mimeType = null, ?string $extension = null): ?string
    {
        $extension = strtolower((string) ($extension ?: pathinfo($filePath, PATHINFO_EXTENSION)));

        return match ($extension) {
            'txt', 'csv', 'md', 'json', 'xml' => $this->plainText($filePath),
            'pdf' => $this->pdf($filePath),
            'docx' => $this->docx($filePath),
            'xlsx' => $this->xlsx($filePath),
            default => str_starts_with((string) $mimeType, 'text/') ? $this->plainText($filePath) : null,
        };
    }

    private function plainText(string $filePath): string
    {
        return trim((string) file_get_contents($filePath));
    }

    private function pdf(string $filePath): ?string
    {
        $binary = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($binary === '') {
            return null;
        }

        $process = new Process([$binary, $filePath, '-']);
        $process->setTimeout(60);
        $process->run();

        return $process->isSuccessful() ? $this->clean($process->getOutput()) : null;
    }

    private function docx(string $filePath): ?string
    {
        $zip = $this->zip($filePath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return null;
        }

        $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);

        return $this->clean(strip_tags($xml));
    }

    private function xlsx(string $filePath): ?string
    {
        $zip = $this->zip($filePath);
        $texts = [];

        $sharedStrings = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStrings !== false) {
            $texts[] = strip_tags(str_replace('</si>', "\n", $sharedStrings));
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheet = $zip->getFromName($name);
                if ($sheet !== false) {
                    $texts[] = strip_tags(str_replace(['</row>', '</c>'], "\n", $sheet));
                }
            }
        }

        $zip->close();

        return $this->clean(implode("\n", $texts));
    }

    private function zip(string $filePath): ZipArchive
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException("Não foi possível abrir o arquivo compactado: {$filePath}");
        }

        return $zip;
    }

    private function clean(string $text): ?string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');
        $text = trim(preg_replace('/\R{3,}/', "\n\n", $text) ?? '');

        return $text === '' ? null : $text;
    }
}
