<?php

namespace App\Support\Help;

use Filament\Actions\Action;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Support\Enums\MaxWidth;

class HelpAction
{
    public static function page(string $slug): Action
    {
        return self::configure(Action::make($slug), $slug);
    }

    public static function form(string $slug): FormAction
    {
        return self::configure(FormAction::make($slug), $slug);
    }

    public static function configure(Action|FormAction $action, string $slug): Action|FormAction
    {
        return $action
            ->label(__('Help'))
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->slideOver()
            ->modalWidth(MaxWidth::Large)
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
