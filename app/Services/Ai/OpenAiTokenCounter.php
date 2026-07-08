<?php

namespace App\Services\Ai;

class OpenAiTokenCounter
{
    public function count(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $characters = mb_strlen($text);
        preg_match_all('/[\pL\pN]+|[^\s\pL\pN]/u', $text, $matches);

        $lexicalEstimate = count($matches[0]);
        $characterEstimate = (int) ceil($characters / 4);

        return max($lexicalEstimate, $characterEstimate);
    }
}
