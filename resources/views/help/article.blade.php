@php
    $exists = $article->found();
    $showDevWarning = ! $exists && app()->environment('local', 'testing');
@endphp

<div>
    @if ($showDevWarning)
        <div class="rounded-md bg-danger-50 dark:bg-danger-950 p-4 text-sm text-danger-700 dark:text-danger-300">
            {{ __("Help article ':slug' was not found.", ['slug' => $slug]) }}
        </div>
    @elseif ($exists)
        @if ($article->usedFallback())
            <div class="mb-4 rounded-md bg-warning-50 dark:bg-warning-950 p-3 text-xs text-warning-700 dark:text-warning-300">
                {{ __("This content isn't available in your language yet — showing it in :locale.", ['locale' => $article->resolvedLocale()]) }}
            </div>
        @endif

        <div class="prose dark:prose-invert max-w-none" style="font-size: 0.85em">
            {!! $article->html() !!}
        </div>
    @endif
</div>
