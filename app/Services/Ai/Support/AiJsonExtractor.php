<?php

namespace App\Services\Ai\Support;

use Illuminate\Support\Str;

/**
 * Tolerant JSON extraction for AI CLI responses: strips markdown code
 * fences and, when the whole text is not valid JSON, falls back to the
 * first balanced {...} object found in it (models often wrap the JSON in
 * prose or emit more than one object).
 */
class AiJsonExtractor
{
    /**
     * @return array<string, mixed>|null
     */
    public function extract(string $response): ?array
    {
        $json = $this->stripCodeFences($response);

        $decoded = json_decode($json, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $first = $this->firstJsonObject($json);

        if ($first !== null) {
            $decoded = json_decode($first, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function stripCodeFences(string $response): string
    {
        $trimmed = trim($response);

        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/```\s*$/', '', $trimmed) ?? $trimmed;
        }

        return trim($trimmed);
    }

    /**
     * Extracts the first balanced {...} object from the text, ignoring
     * braces inside JSON strings.
     */
    private function firstJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = $start, $length = strlen($text); $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
