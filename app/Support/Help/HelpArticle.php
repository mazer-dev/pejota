<?php

namespace App\Support\Help;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class HelpArticle
{
    private const FALLBACK_LOCALE = 'pt_BR';

    private const BASE_DIR = 'help';

    private const SLUG_PATTERN = '/^[a-z0-9-]+$/';

    private const LOCALE_PATTERN = '/^[A-Za-z_-]+$/';

    private bool $found = false;

    private string $resolvedLocale = '';

    private ?string $path = null;

    private ?string $frontmatterTitle = null;

    private string $body = '';

    public function __construct(
        private readonly string $slug,
        private readonly string $locale,
    ) {
        $this->resolve();
    }

    public static function exists(string $slug): bool
    {
        if (! preg_match(self::SLUG_PATTERN, $slug)) {
            return false;
        }

        return (new self($slug, app()->getLocale()))->found();
    }

    public function found(): bool
    {
        return $this->found;
    }

    public function resolvedLocale(): string
    {
        return $this->resolvedLocale;
    }

    public function usedFallback(): bool
    {
        return $this->found && $this->resolvedLocale !== $this->locale;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function title(): string
    {
        if (filled($this->frontmatterTitle)) {
            return $this->frontmatterTitle;
        }

        if (preg_match('/^#\s+(.+)$/m', $this->body, $matches)) {
            return trim($matches[1]);
        }

        return Str::ucfirst(str_replace('-', ' ', $this->slug));
    }

    public function html(): HtmlString
    {
        if (! $this->found) {
            return new HtmlString('');
        }

        try {
            $mtime = filemtime($this->path) ?: 0;
            $html = Cache::rememberForever(
                "help.{$this->slug}.{$this->resolvedLocale}.{$mtime}",
                fn (): string => Str::markdown($this->body, [
                    'html_input' => 'allow',
                    'allow_unsafe_links' => false,
                ]),
            );

            return new HtmlString($html);
        } catch (\Throwable $e) {
            Log::warning('Failed to render help article', [
                'slug' => $this->slug,
                'error' => $e->getMessage(),
            ]);

            return new HtmlString('');
        }
    }

    private function resolve(): void
    {
        if (! preg_match(self::SLUG_PATTERN, $this->slug)) {
            return;
        }

        if (! preg_match(self::LOCALE_PATTERN, $this->locale)) {
            return;
        }

        foreach ([$this->locale, self::FALLBACK_LOCALE] as $candidate) {
            if (! preg_match(self::LOCALE_PATTERN, $candidate)) {
                continue;
            }

            $path = $this->path($candidate);

            if (is_file($path)) {
                $this->found = true;
                $this->resolvedLocale = $candidate;
                $this->path = $path;
                $this->parse((string) file_get_contents($path));

                return;
            }
        }
    }

    private function path(string $locale): string
    {
        return resource_path(self::BASE_DIR."/{$this->slug}/{$locale}.md");
    }

    private function parse(string $raw): void
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $matches)) {
            $frontmatter = $matches[1];
            $this->body = $matches[2];

            if (preg_match('/^title:\s*(.+)$/m', $frontmatter, $titleMatch)) {
                $this->frontmatterTitle = trim($titleMatch[1]);
            }

            return;
        }

        $this->body = $raw;
    }
}
