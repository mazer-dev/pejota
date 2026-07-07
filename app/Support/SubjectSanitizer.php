<?php

namespace App\Support;

class SubjectSanitizer
{
    public static function sanitize(string $subject): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $subject));
    }
}
