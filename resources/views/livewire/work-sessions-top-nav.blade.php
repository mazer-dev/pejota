<div wire:poll.60s
        x-tooltip="{
            content: '@lang('Work sessions') - @lang('Running')',
            theme: $store.theme,
        }"
>
    <a href="{{ \App\Filament\App\Resources\WorkSessionResource::getUrl() }}">
        <span style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);"
              class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary">
            <x-filament::icon
                        icon={{\App\Filament\App\Resources\WorkSessionResource::getNavigationIcon()}}
                        class="h-5 w-5 text-gray-500 dark:text-gray-400"
                />
            <span class="grid">
                <span class="truncate">
                    {{ $count }}
                </span>
            </span>
        </span>
    </a>
</div>
