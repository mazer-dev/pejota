@php
    $isContained = $isContained();
    $striped = $getStriped();
    $showIndex = $getShowIndex();
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div
        {{
            $attributes
                ->merge([
                    'id' => $getId(),
                ], escape: false)
                ->merge($getExtraAttributes(), escape: false)
                ->class(['fi-in-table-repeatable bg-white border border-gray-300 shadow-sm rounded-xl relative dark:bg-gray-800 dark:border-gray-600'])
        }}
    >
        <x-filament::grid
            :default="$getGridColumns('default')"
            :sm="$getGridColumns('sm')"
            :md="$getGridColumns('md')"
            :lg="$getGridColumns('lg')"
            :xl="$getGridColumns('xl')"
            :two-xl="$getGridColumns('2xl')"
            class="gap-4"
        >
            <table class="filament-table-repeatable w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5 shadow rounded-xl ">
                <thead>
                    <tr>
                        @if($showIndex)<th class="filament-table-repeateable-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6"></th>@endif
                        @foreach($getColumnLabels() as $label)

                        <th class="filament-table-repeateable-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">{{ $label['name'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
                    @foreach ($getChildComponentContainers() as $item)
                    <tr class="{{ $striped ? ($loop->index%2 == 0 ? 'bg-gray-50 dark:bg-white/5' : '') : ''}}">
                        @if($showIndex)<td class="filament-table-repeateable-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 text-center py-2">{{ $loop->index }}</td>@endif

                        @foreach($item->getComponents() as $component)
                        <td
                            class="filament-table-repeateable-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 text-center py-2"
                        >
                            {{ $component }}
                        </td>
                        @endforeach

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::grid>
    </div>
</x-dynamic-component>
