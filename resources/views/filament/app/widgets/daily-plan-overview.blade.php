<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between gap-2">
                <span>{{ __('Plan of the day') }}</span>
                <a href="{{ $pageUrl }}" class="text-sm text-primary-600 dark:text-primary-400 hover:underline">
                    {{ __('Open') }}
                </a>
            </div>
        </x-slot>

        @if (! $plan || $plan->isGenerating())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Generating your plan of the day...') }}</p>
        @elseif ($plan->isFailed())
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ __('The plan generation failed.') }}</p>
        @elseif ($totalCount === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Nothing urgent today.') }}</p>
        @else
            <div class="space-y-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __(':done of :total items done', ['done' => $doneCount, 'total' => $totalCount]) }}
                    · {{ __('Planned') }}: {{ \App\Helpers\PejotaHelper::formatDuration($plan->planned_minutes) }}
                </p>

                <ul class="space-y-2">
                    @forelse ($nextItems as $item)
                        <li wire:key="dpo-item-{{ $item->id }}" class="flex items-center gap-2 text-sm">
                            <x-filament::badge :color="$item->type->getColor()" :icon="$item->type->getIcon()" size="sm">
                                {{ \App\Helpers\PejotaHelper::formatDuration($item->estimated_minutes) }}
                            </x-filament::badge>
                            <span>{{ $item->title }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-success-600 dark:text-success-400">{{ __('All items done. Great job!') }}</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
