<?php

namespace App\Support\Help;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;

class HelpAction
{
    public static function page(string $slug): Action
    {
        return self::configure(Action::make($slug), $slug);
    }

    public static function form(string $slug): Action
    {
        return self::configure(Action::make($slug), $slug);
    }

    public static function configure(Action $action, string $slug): Action
    {
        return $action
            ->label(__('Help'))
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::Large)
            ->modalHeading(fn (): string => (new HelpArticle($slug, app()->getLocale()))->title())
            ->modalContent(fn () => view('help.article', [
                'article' => new HelpArticle($slug, app()->getLocale()),
                'slug' => $slug,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->visible(fn (): bool => app()->environment('local', 'testing') || HelpArticle::exists($slug));
    }
}
