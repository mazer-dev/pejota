<?php

namespace Tests\Feature\Help;

use App\Support\Help\HelpArticle;
use Tests\TestCase;

class HelpArticleTest extends TestCase
{
    public function test_resolves_current_locale_when_present(): void
    {
        $article = new HelpArticle('help-test-topic', 'en');

        $this->assertTrue($article->found());
        $this->assertFalse($article->usedFallback());
        $this->assertSame('en', $article->resolvedLocale());
        $this->assertStringContainsString('English content', $article->html()->toHtml());
    }

    public function test_falls_back_to_pt_br_when_locale_missing(): void
    {
        $article = new HelpArticle('help-fallback-only', 'es');

        $this->assertTrue($article->found());
        $this->assertTrue($article->usedFallback());
        $this->assertSame('pt_BR', $article->resolvedLocale());
    }

    public function test_title_from_frontmatter(): void
    {
        $article = new HelpArticle('help-test-topic', 'pt_BR');

        $this->assertSame('Tópico de Teste', $article->title());
    }

    public function test_title_from_first_h1_when_no_frontmatter(): void
    {
        $article = new HelpArticle('help-test-topic', 'en');

        $this->assertSame('English Heading', $article->title());
    }

    public function test_title_humanizes_slug_when_no_frontmatter_or_h1(): void
    {
        $article = new HelpArticle('help-fallback-only', 'pt_BR');

        $this->assertSame('Help fallback only', $article->title());
    }

    public function test_missing_slug_is_not_found(): void
    {
        $article = new HelpArticle('does-not-exist-anywhere', 'pt_BR');

        $this->assertFalse($article->found());
        $this->assertFalse(HelpArticle::exists('does-not-exist-anywhere'));
    }

    public function test_invalid_slug_is_rejected(): void
    {
        $this->assertFalse(HelpArticle::exists('../etc/passwd'));
        $this->assertFalse(HelpArticle::exists('UPPER'));
        $this->assertFalse(HelpArticle::exists('with/slash'));
        $this->assertFalse((new HelpArticle('../etc/passwd', 'pt_BR'))->found());
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $article = new HelpArticle('help-test-topic', '../../../etc/passwd');

        $this->assertFalse($article->found());
    }

    public function test_html_contains_parsed_markup(): void
    {
        $article = new HelpArticle('help-test-topic', 'pt_BR');

        $this->assertStringContainsString('<h1>', $article->html()->toHtml());
    }
}
