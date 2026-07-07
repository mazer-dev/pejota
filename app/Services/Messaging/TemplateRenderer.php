<?php

namespace App\Services\Messaging;

class TemplateRenderer
{
    /**
     * @param  array<string, string>  $context
     */
    public function render(string $template, array $context, bool $html = false): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            function (array $matches) use ($context, $html): string {
                $key = $matches[1];

                if (! array_key_exists($key, $context)) {
                    return $matches[0];
                }

                $value = (string) $context[$key];

                return $html ? e($value) : $value;
            },
            $template
        );
    }
}
