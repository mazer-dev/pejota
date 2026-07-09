@php
    use App\Helpers\PejotaHelper;
    use Illuminate\Contracts\Pagination\CursorPaginator;
    use Illuminate\Contracts\Pagination\Paginator;

    $messages = ($records instanceof Paginator || $records instanceof CursorPaginator)
        ? $records->getCollection()
        : collect($records);

    $messages = $messages->reverse()->values();
    $timezone = PejotaHelper::getUserTimeZone();
@endphp

@php
    $pendingSuggestions = $this->pendingSuggestions();
@endphp

<div wire:poll.10s.visible="refreshMessages" class="bg-gray-50 dark:bg-gray-950">
    @if ($pendingSuggestions->isNotEmpty())
        <div class="px-4 pt-5 sm:px-6">
            <div class="rounded-lg border border-primary-300 bg-primary-50 p-4 shadow-sm dark:border-primary-500/30 dark:bg-primary-500/10">
                <div class="mb-3 flex items-center gap-2">
                    <x-heroicon-o-sparkles class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                        Sugestões da IA
                    </span>
                    <span class="inline-flex items-center rounded-full bg-primary-600 px-2 py-0.5 text-xs font-semibold text-white">
                        {{ $pendingSuggestions->count() }}
                    </span>
                </div>

                <div class="flex flex-col gap-3">
                    @foreach ($pendingSuggestions as $suggestion)
                        <div
                            wire:key="whatsapp-suggestion-{{ $suggestion->id }}"
                            class="rounded-lg bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30">
                                            @if ($suggestion->type === \App\Enums\WhatsappSuggestionTypeEnum::Task)
                                                <x-heroicon-o-clipboard-document-check class="h-3.5 w-3.5" />
                                            @else
                                                <x-heroicon-o-document-text class="h-3.5 w-3.5" />
                                            @endif
                                            {{ $suggestion->type->getLabel() }}
                                        </span>
                                        <span class="break-words text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $suggestion->title }}
                                        </span>
                                    </div>

                                    <p class="whitespace-pre-wrap break-words text-sm text-gray-600 dark:text-gray-300">
                                        {{ $suggestion->content }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="acceptSuggestion({{ $suggestion->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="acceptSuggestion,dismissSuggestion"
                                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"
                                    >
                                        <x-heroicon-o-check class="h-4 w-4" />
                                        Aceitar
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="dismissSuggestion({{ $suggestion->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="acceptSuggestion,dismissSuggestion"
                                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                    >
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                        Descartar
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="px-4 py-5 sm:px-6">
        @if ($messages->isEmpty())
            <div class="flex min-h-32 items-center justify-center rounded-lg border border-dashed border-gray-300 px-6 py-10 text-sm text-gray-500 dark:border-white/10 dark:text-gray-300">
                Nenhuma mensagem sincronizada.
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach ($messages as $message)
                    @php
                        $isSent = (bool) $message->from_me;
                        $attachments = $message->relationLoaded('attachments') ? $message->attachments : collect();
                        $transcriptionText = trim((string) $attachments->pluck('transcription_text')->filter()->implode("\n\n"));
                        $extractedText = trim((string) $attachments->pluck('extracted_text')->filter()->implode("\n\n"));
                        $text = trim((string) $message->text);
                        $displayText = $text !== '' ? $text : ($transcriptionText !== '' ? $transcriptionText : '');
                        $sentAt = $message->sent_at?->copy()->timezone($timezone)->format('d/m/Y H:i');
                        $type = $message->message_type ? str($message->message_type)->replace('_', ' ')->headline() : null;
                    @endphp

                    <div @class([
                        'flex w-full',
                        'justify-end' => $isSent,
                        'justify-start' => ! $isSent,
                    ])>
                        <div
                            @class([
                                'max-w-2xl rounded-lg px-4 py-3 shadow-sm',
                                'bg-primary-600 text-white' => $isSent,
                                'bg-gray-800 text-gray-50 ring-1 ring-white/10' => ! $isSent,
                            ])
                        >
                            <div @class([
                                'mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs',
                                'text-primary-100' => $isSent,
                                'text-gray-300' => ! $isSent,
                            ])>
                                <span class="font-medium">
                                    {{ $isSent ? 'Você' : 'Cliente' }}
                                </span>

                                @if ($sentAt)
                                    <span>{{ $sentAt }}</span>
                                @endif
                            </div>

                            @foreach ($attachments as $attachment)
                                @php
                                    $isImage = str_starts_with((string) $attachment->mime_type, 'image/') && filled($attachment->path);
                                    $isAudio = str_starts_with((string) $attachment->mime_type, 'audio/') && filled($attachment->path);
                                    $isStored = filled($attachment->path);
                                @endphp

                                @if ($isImage)
                                    <a href="{{ route('whatsapp.attachments.show', $attachment) }}" target="_blank" class="mb-3 block overflow-hidden rounded-md ring-1 ring-black/10 dark:ring-white/10">
                                        <img
                                            src="{{ route('whatsapp.attachments.show', $attachment) }}"
                                            alt="{{ $attachment->original_filename ?: 'Imagem enviada no WhatsApp' }}"
                                            class="max-h-96 w-full object-contain"
                                            style="max-height: 24rem;"
                                            loading="lazy"
                                        >
                                    </a>
                                @elseif ($isAudio)
                                    <audio controls preload="none" class="mb-3 w-full">
                                        <source src="{{ route('whatsapp.attachments.show', $attachment) }}" type="{{ $attachment->mime_type }}">
                                    </audio>
                                @elseif ($isStored)
                                    <a
                                        href="{{ route('whatsapp.attachments.show', $attachment) }}"
                                        target="_blank"
                                        @class([
                                            'mb-3 inline-flex items-center gap-2 rounded-md px-3 py-2 text-xs font-medium ring-1',
                                            'bg-white/10 text-white ring-white/20 hover:bg-white/15' => $isSent,
                                            'bg-white/10 text-gray-100 ring-white/15 hover:bg-white/15' => ! $isSent,
                                        ])
                                    >
                                        <x-heroicon-o-document-arrow-down class="h-4 w-4" />
                                        {{ $attachment->original_filename ?: 'Baixar anexo' }}
                                    </a>
                                @endif
                            @endforeach

                            @if ($displayText !== '')
                                <div class="whitespace-pre-wrap break-words text-sm leading-6">
                                    {{ $displayText }}
                                </div>
                            @else
                                <div @class([
                                    'text-sm italic',
                                    'text-primary-100' => $isSent,
                                    'text-gray-300' => ! $isSent,
                                ])>
                                    Mensagem sem texto.
                                </div>
                            @endif

                            @if ($text === '' && $transcriptionText !== '')
                                <div @class([
                                    'mt-2 text-xs',
                                    'text-primary-100' => $isSent,
                                    'text-gray-300' => ! $isSent,
                                ])>
                                    Transcrição de áudio
                                </div>
                            @endif

                            @if ($attachments->count() > 0)
                                <div @class([
                                    'mt-2 text-xs',
                                    'text-primary-100' => $isSent,
                                    'text-gray-300' => ! $isSent,
                                ])>
                                    {{ trans_choice('{1} :count anexo|[2,*] :count anexos', $attachments->count(), ['count' => $attachments->count()]) }}
                                </div>
                            @endif

                            @if ($extractedText !== '')
                                <details @class([
                                    'mt-2 text-xs',
                                    'text-primary-100' => $isSent,
                                    'text-gray-300' => ! $isSent,
                                ])>
                                    <summary class="cursor-pointer font-medium">Conteúdo processado do anexo</summary>
                                    <div class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap rounded-md bg-black/10 p-2">
                                        {{ $extractedText }}
                                    </div>
                                </details>
                            @endif

                            <div @class([
                                'mt-2 flex flex-wrap items-center gap-2 text-xs',
                                'text-primary-100' => $isSent,
                                'text-gray-300' => ! $isSent,
                            ])>
                                @if ($type)
                                    <span>{{ $type }}</span>
                                @endif

                                @if ($message->status)
                                    <span>{{ $message->status }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <form wire:submit.prevent="sendComposerMessage" x-data="{ dictate: false }" class="sticky bottom-0 z-20 border-t border-gray-200 bg-white px-4 py-4 shadow-lg dark:border-white/10 dark:bg-gray-900 sm:px-6">
        <div class="flex flex-col gap-3">
            <div
                x-show="dictate"
                x-cloak
                x-transition
                class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5"
            >
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    Diga à IA o que você quer comunicar ao cliente:
                </label>
                <textarea
                    wire:model="aiInstruction"
                    rows="2"
                    placeholder="Ex.: avisar que a entrega vai atrasar 2 dias e propor nova data na sexta"
                    class="block w-full resize-y rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm transition duration-75 placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500"
                ></textarea>
                <div>
                    <button
                        type="button"
                        wire:click="generateAiFromInstruction"
                        wire:loading.attr="disabled"
                        wire:target="generateAiFromInstruction"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"
                    >
                        <x-heroicon-o-sparkles class="h-4 w-4" />
                        <span wire:loading.remove wire:target="generateAiFromInstruction">Gerar mensagem</span>
                        <span wire:loading wire:target="generateAiFromInstruction">Gerando...</span>
                    </button>
                </div>
            </div>
            <textarea
                wire:model.live.debounce.500ms="composerMessage"
                rows="3"
                placeholder="Escreva uma mensagem ou gere uma sugestão com IA..."
                class="block w-full resize-y rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm transition duration-75 placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500"
            ></textarea>

            @error('composerMessage')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div x-data class="flex flex-wrap items-center gap-2">
                    <input
                        x-ref="attachment"
                        wire:model="composerAttachment"
                        type="file"
                        class="hidden"
                    >

                    <button
                        type="button"
                        x-on:click="$refs.attachment.click()"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-paper-clip class="h-4 w-4" />
                        Anexo
                    </button>

                    <button
                        type="button"
                        wire:click="generateAiSuggestion"
                        wire:loading.attr="disabled"
                        wire:target="generateAiSuggestion"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-sparkles class="h-4 w-4" />
                        <span wire:loading.remove wire:target="generateAiSuggestion">Sugerir resposta</span>
                        <span wire:loading wire:target="generateAiSuggestion">Gerando...</span>
                    </button>

                    <button
                        type="button"
                        @click="dictate = ! dictate"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                        <span x-text="dictate ? 'Fechar ditado' : 'Ditar mensagem'">Ditar mensagem</span>
                    </button>
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="sendComposerMessage,composerAttachment"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"
                >
                    <x-heroicon-o-paper-airplane class="h-4 w-4" />
                    <span wire:loading.remove wire:target="sendComposerMessage">Enviar</span>
                    <span wire:loading wire:target="sendComposerMessage">Enviando...</span>
                </button>
            </div>

            <div wire:loading wire:target="composerAttachment" class="text-sm text-gray-500 dark:text-gray-400">
                Anexando arquivo...
            </div>

            @if ($this->composerAttachment)
                <div class="flex flex-wrap items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-200">
                    <x-heroicon-o-paper-clip class="h-4 w-4" />
                    <span class="break-all">{{ $this->composerAttachment->getClientOriginalName() }}</span>
                    <button type="button" wire:click="removeComposerAttachment" class="font-medium text-danger-600 hover:underline dark:text-danger-400">
                        Remover
                    </button>
                </div>
            @endif

            @error('composerAttachment')
                <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
        </div>
    </form>
</div>
