@php
    /**
     * Livewire 3 derives child component keys from the compiled view path, so
     * every render of this view shares the same key. When one page renders it
     * more than once (view page + action modal), the second render becomes an
     * empty stub with a duplicated wire:id and clicks are dispatched to the
     * wrong component ("Public method [create] not found on component").
     * A per-record key keeps each instance apart.
     */
    $commentsRecord = $record ?? $entry?->getRecord() ?? $component?->getRecord() ?? (method_exists($this, 'getRecord') ? $this->getRecord() : (property_exists($this, 'record') ? $this->record : null));
@endphp

<livewire:comments
    :record="$commentsRecord"
    :key="'filament-comments-' . $commentsRecord?->getMorphClass() . '-' . $commentsRecord?->getKey()"
/>
