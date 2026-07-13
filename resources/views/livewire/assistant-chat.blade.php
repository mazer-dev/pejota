<div
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    class="fixed bottom-4 end-4 z-50"
>
    <button
        type="button"
        @click="open = ! open"
        x-tooltip="{ content: '@lang('Data assistant')', theme: $store.theme }"
        class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50"
    >
        <x-filament::icon
            icon="heroicon-o-chat-bubble-left-right"
            class="h-6 w-6"
        />
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        @click.outside="open = false"
        class="fixed bottom-20 end-4 flex flex-col overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        style="width: min(calc(100vw - 2rem), 26rem); height: min(calc(100vh - 8rem), 34rem);"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 dark:border-white/10">
            <h3 class="truncate text-sm font-semibold text-gray-700 dark:text-gray-200">
                @lang('Data assistant')
                <span class="ms-1 text-xs font-normal text-gray-400">@lang('read-only + invoices')</span>
            </h3>

            <div class="flex items-center gap-1" x-data="{ history: false }">
                <div class="relative">
                    <button
                        type="button"
                        @click="history = ! history"
                        x-tooltip="{ content: '@lang('Previous conversations')', theme: $store.theme }"
                        class="rounded-md p-1 text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5"
                    >
                        <x-filament::icon icon="heroicon-m-clock" class="h-5 w-5" />
                    </button>

                    <div
                        x-show="history"
                        x-cloak
                        @click.outside="history = false"
                        x-transition
                        class="absolute end-0 z-10 mt-1 max-h-60 w-64 overflow-y-auto rounded-md bg-white p-1 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10"
                    >
                        @forelse ($conversations as $pastConversation)
                            <button
                                type="button"
                                wire:key="conv-{{ $pastConversation->id }}"
                                wire:click="continueConversation({{ $pastConversation->id }})"
                                @click="history = false"
                                class="block w-full truncate rounded px-2 py-1 text-start text-xs {{ $pastConversation->id === $conversationId ? 'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5' }}"
                            >
                                {{ $pastConversation->title ?? __('Conversation') }}
                                <span class="text-gray-400">- {{ $pastConversation->updated_at->diffForHumans() }}</span>
                            </button>
                        @empty
                            <p class="px-2 py-1 text-xs text-gray-400">@lang('No previous conversations.')</p>
                        @endforelse
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="newConversation"
                    x-tooltip="{ content: '@lang('New conversation')', theme: $store.theme }"
                    class="rounded-md p-1 text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5"
                >
                    <x-filament::icon icon="heroicon-m-plus" class="h-5 w-5" />
                </button>

                <button
                    type="button"
                    @click="open = false"
                    class="rounded-md p-1 text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5"
                >
                    <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                </button>
            </div>
        </div>

        {{-- Messages --}}
        <div
            class="flex-1 space-y-2 overflow-y-auto p-3"
            @if ($pending) wire:poll.2s="pollTick" @endif
            x-ref="messages"
            x-init="$nextTick(() => $refs.messages.scrollTop = $refs.messages.scrollHeight)"
            @assistant-chat-scroll.window="$nextTick(() => $refs.messages.scrollTop = $refs.messages.scrollHeight)"
        >
            @forelse ($messages as $chatMessage)
                @if ($chatMessage->role === 'user')
                    <div
                        wire:key="msg-{{ $chatMessage->id }}"
                        class="ms-6 space-y-2 rounded-lg bg-primary-50 px-3 py-2 text-sm text-gray-700 dark:bg-primary-400/10 dark:text-gray-200"
                    >
                        @if ($chatMessage->attachments->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($chatMessage->attachments as $attachment)
                                    @php
                                        $isImage = $attachment->isImage();
                                        $isInline = $isImage || $attachment->isPdf();
                                        $attachmentUrl = route('assistant.attachments.show', $attachment);
                                    @endphp

                                    <a
                                        wire:key="attachment-{{ $attachment->id }}"
                                        href="{{ $attachmentUrl }}"
                                        target="_blank"
                                        rel="noopener"
                                        @unless ($isInline) download @endunless
                                        class="flex max-w-[10rem] flex-col overflow-hidden rounded-md bg-white ring-1 ring-gray-950/10 hover:ring-primary-400 dark:bg-gray-800 dark:ring-white/10"
                                    >
                                        @if ($isImage)
                                            <img
                                                src="{{ $attachmentUrl }}"
                                                alt="{{ $attachment->original_filename ?: __('Attachment') }}"
                                                class="h-16 w-full object-cover"
                                                loading="lazy"
                                            >
                                        @else
                                            <div class="flex h-16 w-full items-center justify-center bg-gray-50 dark:bg-white/5">
                                                <x-filament::icon
                                                    :icon="$attachment->isPdf() ? 'heroicon-o-document-text' : 'heroicon-o-document'"
                                                    class="h-6 w-6 text-gray-400"
                                                />
                                            </div>
                                        @endif

                                        <div class="flex flex-col gap-0.5 px-1.5 py-1 text-[11px] leading-tight">
                                            <span class="truncate text-gray-700 dark:text-gray-200">{{ $attachment->original_filename ?: __('File') }}</span>
                                            <span class="text-gray-400">{{ $attachment->humanSize() }}</span>

                                            @if ($attachment->status === \App\Models\AssistantMessageAttachment::STATUS_ERROR)
                                                <span class="font-medium text-danger-600 dark:text-danger-400">@lang('Failed')</span>
                                            @elseif (in_array($attachment->status, [\App\Models\AssistantMessageAttachment::STATUS_STORED, \App\Models\AssistantMessageAttachment::STATUS_PROCESSING], true))
                                                <span class="text-gray-400">@lang('Processing...')</span>
                                            @endif
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <div class="whitespace-pre-wrap">{{ $chatMessage->content }}</div>
                    </div>
                @else
                    <div
                        wire:key="msg-{{ $chatMessage->id }}"
                        class="prose prose-sm dark:prose-invert me-6 max-w-none rounded-lg bg-gray-50 px-3 py-2 text-gray-700 dark:bg-white/5 dark:text-gray-200"
                    >{!! $chatMessage->contentHtml() !!}</div>
                @endif
            @empty
                <p class="p-2 text-center text-xs text-gray-400">
                    @lang('Ask anything about your clients, tasks, invoices and sessions. The assistant reads your data and can create an invoice, but only after you confirm it with a passphrase.')
                </p>
            @endforelse

            @if ($pending)
                <div class="me-6 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-400 dark:bg-white/5">
                    <x-filament::loading-indicator class="h-4 w-4" />
                    @if ($pendingProcessingAttachments)
                        @lang('Analyzing attachments...')
                    @else
                        @lang('Thinking...')
                    @endif
                </div>
            @endif
        </div>

        {{-- Chips --}}
        <div class="flex flex-wrap gap-1 border-t border-gray-100 px-3 py-2 dark:border-white/10">
            @foreach ($chips as $chipKey => $chipLabel)
                <button
                    type="button"
                    wire:key="chip-{{ $chipKey }}"
                    wire:click="runChip('{{ $chipKey }}')"
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10"
                >
                    {{ $chipLabel }}
                </button>
            @endforeach
        </div>

        {{-- Input --}}
        <div class="border-t border-gray-100 p-2 dark:border-white/10">
            @if ($attachmentError)
                <p class="mb-2 text-xs text-danger-600 dark:text-danger-400">{{ $attachmentError }}</p>
            @endif

            @if (! empty($attachments))
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach ($attachments as $index => $pendingAttachment)
                        <div
                            wire:key="pending-attachment-{{ $index }}"
                            class="flex items-center gap-2 rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-white/5 dark:text-gray-200"
                        >
                            <x-filament::icon icon="heroicon-m-paper-clip" class="h-3.5 w-3.5 shrink-0" />
                            <span class="max-w-[8rem] truncate">{{ $pendingAttachment->getClientOriginalName() }}</span>
                            <span class="text-gray-400">{{ number_format($pendingAttachment->getSize() / 1024, 0) }} KB</span>
                            <button
                                type="button"
                                wire:click="removeAttachment({{ $index }})"
                                class="text-gray-400 hover:text-danger-600 dark:hover:text-danger-400"
                            >
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div wire:loading wire:target="attachments" class="mb-2 flex items-center gap-2 text-xs text-gray-400">
                <x-filament::loading-indicator class="h-3.5 w-3.5" />
                @lang('Uploading file...')
            </div>

            <form wire:submit="send" class="flex items-center gap-2">
                @if ($attachmentsEnabled)
                    <input
                        x-ref="attachmentInput"
                        type="file"
                        multiple
                        wire:model="attachments"
                        accept="{{ collect($allowedAttachmentExtensions)->map(fn (string $extension): string => '.'.$extension)->implode(',') }}"
                        class="hidden"
                    >

                    <button
                        type="button"
                        @click="$refs.attachmentInput.click()"
                        x-tooltip="{ content: '@lang('Attach file')', theme: $store.theme }"
                        @disabled($pending || count($attachments) >= $maxAttachmentFiles)
                        class="shrink-0 rounded-md p-2 text-gray-500 hover:bg-gray-50 disabled:opacity-40 dark:text-gray-400 dark:hover:bg-white/5"
                    >
                        <x-filament::icon icon="heroicon-o-paper-clip" class="h-4 w-4" />
                    </button>
                @endif

                <input
                    type="text"
                    wire:model="message"
                    placeholder="{{ __('Ask about your data...') }}"
                    @disabled($pending)
                    class="fi-input block w-full rounded-md border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                />
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="attachments,send"
                    @disabled($pending)
                    class="shrink-0 rounded-md bg-primary-600 p-2 text-white hover:bg-primary-500 disabled:opacity-50"
                >
                    <x-filament::icon icon="heroicon-m-paper-airplane" class="h-4 w-4" />
                </button>
            </form>
        </div>
    </div>
</div>
