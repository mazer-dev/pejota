<x-filament-panels::page>
    {{ $this->table }}

    @php($pending = $this->pendingInvitations())

    @if ($pending->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ __('Pending invitations') }}</x-slot>

            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($pending as $invitation)
                    <div wire:key="invitation-{{ $invitation->id }}"
                         class="flex items-center justify-between gap-4 py-2">
                        <div class="text-sm">
                            <span class="font-medium">{{ $invitation->email }}</span>
                            <span class="text-gray-500">· {{ __(ucfirst($invitation->role->value)) }}</span>
                            <span class="text-gray-400">· {{ __('expires :date', ['date' => $invitation->expires_at->diffForHumans()]) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::button size="sm" color="gray" wire:click="resendInvitation({{ $invitation->id }})">
                                {{ __('Resend') }}
                            </x-filament::button>
                            <x-filament::button size="sm" color="danger"
                                wire:click="revokeInvitation({{ $invitation->id }})"
                                wire:confirm="{{ __('Revoke this invitation?') }}">
                                {{ __('Revoke') }}
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
