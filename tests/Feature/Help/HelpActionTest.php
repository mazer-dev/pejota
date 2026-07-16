<?php

namespace Tests\Feature\Help;

use App\Support\Help\HelpAction;
use Filament\Actions\Action;
use Tests\TestCase;

class HelpActionTest extends TestCase
{
    public function test_page_returns_filament_action_configured(): void
    {
        $action = HelpAction::page('help-test-topic');

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('help-test-topic', $action->getName());
        $this->assertTrue($action->isModalSlideOver());
    }

    public function test_form_returns_form_action(): void
    {
        $action = HelpAction::form('help-test-topic');

        $this->assertInstanceOf(Action::class, $action);
        $this->assertTrue($action->isModalSlideOver());
    }

    public function test_hidden_in_production_when_article_missing(): void
    {
        $this->app['env'] = 'production';

        $action = HelpAction::page('does-not-exist-anywhere');

        $this->assertHidden($action);
    }

    public function test_visible_in_local_even_when_article_missing(): void
    {
        $this->app['env'] = 'local';

        $action = HelpAction::page('does-not-exist-anywhere');

        $this->assertFalse($action->isHidden());
    }

    private function assertHidden(Action $action): void
    {
        $this->assertTrue($action->isHidden());
    }
}
