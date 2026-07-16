<x-filament-panels::page>
    <div>
        <form
            id="form"
            wire:submit="save"
            class="space-y-6"
        >
            {{ $this->form }}

            <x-filament::actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </form>

        <x-filament-actions::modals/>
    </div>
</x-filament-panels::page>
