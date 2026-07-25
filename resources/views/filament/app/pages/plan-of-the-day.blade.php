<x-filament-panels::page>
    @php($plan = $this->plan)

    @if (! $plan)
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-8 text-center space-y-3">
            <p class="text-lg font-semibold">{{ __('No plan for today yet.') }}</p>
            <p class="text-gray-500 dark:text-gray-400">
                {{ __('Generate the plan and the AI will analyze all your tasks, conversations, invoices, contracts and habits to guide your day.') }}
            </p>
        </div>
    @elseif ($plan->isGenerating())
        <div wire:poll.5s="$refresh" class="rounded-lg border border-gray-200 dark:border-white/10 p-8 text-center space-y-3">
            <x-filament::loading-indicator class="h-8 w-8 mx-auto" />
            <p class="text-lg font-semibold">{{ __('Generating your plan of the day...') }}</p>
            <p class="text-gray-500 dark:text-gray-400">
                {{ __('The AI is crossing every piece of data in PeJota. This can take a few minutes.') }}
            </p>
        </div>
    @elseif ($plan->isFailed())
        <div class="rounded-lg border border-danger-300 dark:border-danger-500/40 bg-danger-50 dark:bg-danger-500/10 p-6 space-y-2">
            <p class="font-semibold text-danger-700 dark:text-danger-400">{{ __('The plan generation failed.') }}</p>
            @if ($plan->failure_reason)
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $plan->failure_reason }}</p>
            @endif
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Use the button above to try again.') }}</p>
        </div>
    @else
        <div class="space-y-6">
            <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::badge :color="$plan->isLight() ? 'gray' : 'success'">
                        {{ $plan->mode->getLabel() }}
                    </x-filament::badge>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Generated at') }} {{ $plan->generated_at?->timezone(\App\Helpers\PejotaHelper::getUserTimeZoneOrDefault())->format(\App\Helpers\PejotaHelper::getUserDateTimeFormat()) }}
                    </span>
                </div>

                @if ($plan->summary)
                    <p class="text-base">{{ $plan->summary }}</p>
                @endif

                @foreach (($plan->warnings ?? []) as $warning)
                    <p class="text-sm text-warning-600 dark:text-warning-400">⚠️ {{ $warning }}</p>
                @endforeach
            </div>

            @if ($plan->items->isEmpty())
                <div class="rounded-lg border border-gray-200 dark:border-white/10 p-8 text-center">
                    <p class="text-lg font-semibold">{{ __('Nothing urgent today.') }}</p>
                    <p class="text-gray-500 dark:text-gray-400">{{ __('Enjoy your day off!') }}</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($plan->items as $item)
                        <div wire:key="plan-item-{{ $item->id }}"
                             @class([
                                 'rounded-lg border p-4 flex flex-col gap-2',
                                 'border-gray-200 dark:border-white/10' => $item->isPending(),
                                 'border-success-300 dark:border-success-500/40 opacity-60' => $item->status === \App\Enums\DailyPlanItemStatusEnum::DONE,
                                 'border-gray-200 dark:border-white/10 opacity-40' => $item->status === \App\Enums\DailyPlanItemStatusEnum::SKIPPED,
                             ])>
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="flex items-start gap-3">
                                    <span class="text-lg font-bold text-gray-400 dark:text-gray-500">{{ $item->position }}</span>
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-filament::badge :color="$item->type->getColor()" :icon="$item->type->getIcon()">
                                                {{ $item->type->getLabel() }}
                                            </x-filament::badge>
                                            <span class="font-semibold @if(! $item->isPending()) line-through @endif">
                                                @if ($url = $this->itemUrl($item))
                                                    <a href="{{ $url }}" class="hover:underline">{{ $item->title }}</a>
                                                @else
                                                    {{ $item->title }}
                                                @endif
                                            </span>
                                            <x-filament::badge color="gray">
                                                {{ \App\Helpers\PejotaHelper::formatDuration($item->estimated_minutes) }}
                                            </x-filament::badge>
                                        </div>
                                        @if ($item->reason)
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $item->reason }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if ($item->isPending())
                                        <x-filament::button size="sm" color="success" icon="heroicon-o-check"
                                                            wire:click="markItemDone({{ $item->id }})">
                                            {{ __('Done') }}
                                        </x-filament::button>
                                        <x-filament::button size="sm" color="gray" outlined
                                                            wire:click="skipItem({{ $item->id }})">
                                            {{ __('Skip') }}
                                        </x-filament::button>
                                    @else
                                        <x-filament::button size="sm" color="gray" outlined icon="heroicon-o-arrow-uturn-left"
                                                            wire:click="reopenItem({{ $item->id }})">
                                            {{ __('Reopen') }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>

                            @if ($item->suggested_message)
                                <div x-data="{ copied: false }"
                                     class="rounded-md bg-gray-50 dark:bg-white/5 p-3 text-sm flex items-start justify-between gap-3">
                                    <p class="whitespace-pre-line">{{ $item->suggested_message }}</p>
                                    <x-filament::button size="sm" color="gray" outlined icon="heroicon-o-clipboard"
                                                        x-on:click="navigator.clipboard.writeText(@js($item->suggested_message)); copied = true; setTimeout(() => copied = false, 2000)">
                                        <span x-show="! copied">{{ __('Copy') }}</span>
                                        <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                                    </x-filament::button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4 flex flex-wrap gap-6 text-sm">
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Capacity') }}:</span>
                        <span class="font-semibold">{{ \App\Helpers\PejotaHelper::formatDuration($plan->capacity_minutes) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Planned') }}:</span>
                        <span class="font-semibold">{{ \App\Helpers\PejotaHelper::formatDuration($plan->planned_minutes) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Worked today') }}:</span>
                        <span class="font-semibold">{{ \App\Helpers\PejotaHelper::formatDuration($this->workedTodayMinutes) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Remaining in plan') }}:</span>
                        <span class="font-semibold">{{ \App\Helpers\PejotaHelper::formatDuration($plan->pendingMinutes()) }}</span>
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
