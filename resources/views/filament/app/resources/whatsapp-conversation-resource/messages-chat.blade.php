@php
    use App\Helpers\PejotaHelper;
    use Illuminate\Contracts\Pagination\CursorPaginator;
    use Illuminate\Contracts\Pagination\Paginator;

    $messages = ($records instanceof Paginator || $records instanceof CursorPaginator)
        ? $records->getCollection()
        : collect($records);

    $messages = $messages->reverse()->values();
    $timezone = PejotaHelper::getUserTimeZoneOrDefault(config('app.timezone', 'UTC'));
    $isGroupConversation = (bool) $this->getOwnerRecord()->is_group;
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

                    <div wire:key="whatsapp-message-{{ $message->id }}" @class([
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
                                @php
                                    $authorLabel = $isSent
                                        ? 'Você'
                                        : ($isGroupConversation
                                            ? (trim((string) $message->sender_name) ?: (trim((string) $message->sender_jid) ?: 'Participante'))
                                            : 'Cliente');
                                @endphp

                                <span class="font-medium">
                                    {{ $authorLabel }}
                                </span>

                                @if ($sentAt)
                                    <span>{{ $sentAt }}</span>
                                @endif

                                @if ($isSent)
                                    <span class="ml-auto inline-flex items-center gap-1">
                                        @if ($text !== '')
                                            <button
                                                type="button"
                                                wire:click="startEditingMessage({{ $message->id }})"
                                                title="Editar mensagem no WhatsApp"
                                                class="rounded p-1 transition hover:bg-white/20"
                                            >
                                                <x-heroicon-o-pencil class="h-3.5 w-3.5" />
                                            </button>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="deleteMessage({{ $message->id }})"
                                            wire:confirm="Excluir esta mensagem para todos no WhatsApp? Esta ação não pode ser desfeita."
                                            title="Excluir mensagem para todos"
                                            class="rounded p-1 transition hover:bg-white/20"
                                        >
                                            <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                        </button>
                                    </span>
                                @endif
                            </div>

                            @foreach ($attachments as $attachment)
                                @php
                                    $isImage = str_starts_with((string) $attachment->mime_type, 'image/') && filled($attachment->path);
                                    $isAudio = str_starts_with((string) $attachment->mime_type, 'audio/') && filled($attachment->path);
                                    $isStored = filled($attachment->path);
                                    $audioMimeType = str((string) $attachment->mime_type)->before(';')->trim()->toString() ?: 'audio/ogg';
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
                                    <audio controls preload="metadata" class="mb-3 w-full">
                                        <source src="{{ route('whatsapp.attachments.show', $attachment) }}" type="{{ $audioMimeType }}">
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

                            @if ($this->editingMessageId === $message->id)
                                <div class="flex flex-col gap-2">
                                    <textarea
                                        wire:model="editingMessageText"
                                        rows="3"
                                        class="block w-full resize-y rounded-lg border-0 bg-white/95 text-sm text-gray-950 shadow-sm focus:ring-2 focus:ring-white"
                                    ></textarea>

                                    @error('editingMessageText')
                                        <p class="text-xs font-medium text-white">{{ $message }}</p>
                                    @enderror

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="saveEditedMessage"
                                            wire:loading.attr="disabled"
                                            wire:target="saveEditedMessage"
                                            class="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-primary-700 shadow-sm transition hover:bg-primary-50 disabled:opacity-60"
                                        >
                                            <x-heroicon-o-check class="h-3.5 w-3.5" />
                                            <span wire:loading.remove wire:target="saveEditedMessage">Salvar edição</span>
                                            <span wire:loading wire:target="saveEditedMessage">Salvando...</span>
                                        </button>

                                        <button
                                            type="button"
                                            wire:click="cancelEditingMessage"
                                            class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium text-white ring-1 ring-white/40 transition hover:bg-white/10"
                                        >
                                            Cancelar
                                        </button>
                                    </div>
                                </div>
                            @elseif ($displayText !== '')
                                <div class="whitespace-pre-wrap break-words text-sm leading-6">{{ $displayText }}</div>
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
                                    <div class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap rounded-md bg-black/10 p-2">{{ $extractedText }}</div>
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

                    <button
                        type="button"
                        wire:click="openAiQuestionModal"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-chat-bubble-left-ellipsis class="h-4 w-4" />
                        Perguntar à IA
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

    @if ($this->showAiQuestionModal)
        <div
            wire:key="whatsapp-ai-question-modal-{{ $this->getOwnerRecord()->getKey() }}"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 p-4"
            wire:click.self="closeAiQuestionModal"
        >
            <div class="flex max-h-[90vh] w-full max-w-2xl flex-col gap-4 overflow-y-auto rounded-xl bg-white p-5 shadow-2xl dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Perguntar à IA</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            A resposta usa somente o histórico completo desta conversa e os dados vinculados. Nada será salvo ou enviado.
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeAiQuestionModal"
                        class="rounded-lg p-1.5 text-gray-500 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/10"
                        aria-label="Fechar"
                    >
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div>
                    <label for="whatsapp-ai-question" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Pergunta</label>
                    <textarea
                        id="whatsapp-ai-question"
                        wire:model="aiQuestion"
                        rows="4"
                        maxlength="4000"
                        placeholder="Ex.: qual prazo foi combinado para a próxima entrega?"
                        class="block w-full resize-y rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-950 dark:text-white"
                    ></textarea>
                    <div class="mt-1 flex items-start justify-between gap-3">
                        @error('aiQuestion')
                            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @else
                            <span></span>
                        @enderror
                        <span class="text-xs text-gray-400">máximo de 4.000 caracteres</span>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="button"
                        wire:click="askAiQuestion"
                        wire:loading.attr="disabled"
                        wire:target="askAiQuestion"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"
                    >
                        <x-heroicon-o-sparkles class="h-4 w-4" />
                        <span wire:loading.remove wire:target="askAiQuestion">Perguntar</span>
                        <span wire:loading wire:target="askAiQuestion">Consultando...</span>
                    </button>
                </div>

                @if ($this->aiAnswer !== null)
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                        <div class="mb-2 text-sm font-semibold text-gray-900 dark:text-white">Resposta</div>
                        <div class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-700 dark:text-gray-200">{{ $this->aiAnswer }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
