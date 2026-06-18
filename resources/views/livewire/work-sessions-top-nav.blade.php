<div
    x-data="{ open: false }"
    class="relative"
    wire:poll.30s
>
    <button
        type="button"
        @click="open = ! open"
        x-tooltip="{ content: '@lang('Work sessions') - @lang('Running')', theme: $store.theme }"
    >
        <span style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);"
              class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary">
            <x-filament::icon
                icon="{{ \App\Filament\App\Resources\WorkSessionResource::getNavigationIcon() }}"
                class="h-5 w-5 text-gray-500 dark:text-gray-400"
            />
            <span class="grid"><span class="truncate">{{ $count }}</span></span>
        </span>
    </button>

    <div
        x-show="open"
        x-cloak
        @click.outside="open = false"
        x-transition
        class="absolute end-0 z-50 mt-2 w-80 rounded-lg bg-white p-3 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    >
        <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
            @lang('Running')
        </h3>

        <ul class="mb-3 flex flex-col gap-2">
            @forelse ($running as $session)
                <li wire:key="ws-{{ $session->id }}"
                    class="flex items-center justify-between gap-2 rounded-md bg-gray-50 px-2 py-1 dark:bg-white/5">
                    <div class="min-w-0">
                        <p class="truncate text-sm text-gray-800 dark:text-gray-100">{{ $session->title }}</p>
                        <p class="truncate text-xs text-gray-500">
                            {{ $session->client?->labelName }}
                            <span
                                x-data="{
                                    start: {{ $session->start->timestamp }},
                                    text: '00:00',
                                    tick() {
                                        const s = Math.max(0, Math.floor(Date.now() / 1000) - this.start);
                                        const h = String(Math.floor(s / 3600)).padStart(2, '0');
                                        const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
                                        this.text = h + ':' + m;
                                    },
                                }"
                                x-init="tick(); setInterval(() => tick(), 30000)"
                                x-text="text"
                                class="font-mono"
                            ></span>
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="stopSession({{ $session->id }})"
                        class="rounded-md bg-danger-600 px-2 py-1 text-xs font-medium text-white hover:bg-danger-500"
                    >
                        @lang('Stop')
                    </button>
                </li>
            @empty
                <li class="text-xs text-gray-500">@lang('No running sessions')</li>
            @endforelse
        </ul>

        <div class="flex flex-col gap-2 border-t border-gray-100 pt-2 dark:border-white/10">
            <input
                type="text"
                wire:model="newTitle"
                placeholder="{{ __('Title') }}"
                class="fi-input block w-full rounded-md border-gray-300 text-sm dark:bg-white/5 dark:text-white"
            />
            <select wire:model.live="newClient"
                    class="fi-select block w-full rounded-md border-gray-300 text-sm dark:bg-white/5 dark:text-white">
                <option value="">{{ __('Client') }}</option>
                @foreach ($clientOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model="newProject"
                    class="fi-select block w-full rounded-md border-gray-300 text-sm dark:bg-white/5 dark:text-white">
                <option value="">{{ __('Project') }}</option>
                @foreach ($projectOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model="newTask"
                    class="fi-select block w-full rounded-md border-gray-300 text-sm dark:bg-white/5 dark:text-white">
                <option value="">{{ __('Task') }}</option>
                @foreach ($taskOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <button
                type="button"
                wire:click="startSession"
                class="rounded-md bg-primary-600 px-2 py-1.5 text-sm font-medium text-white hover:bg-primary-500"
            >
                + @lang('Start')
            </button>
        </div>
    </div>
</div>
