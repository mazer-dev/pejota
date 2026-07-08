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

<div class="bg-gray-50 px-4 py-5 dark:bg-gray-950 sm:px-6">
    @if ($messages->isEmpty())
        <div class="flex min-h-32 items-center justify-center rounded-lg border border-dashed border-gray-300 px-6 py-10 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            Nenhuma mensagem sincronizada.
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($messages as $message)
                @php
                    $isSent = (bool) $message->from_me;
                    $text = trim((string) $message->text);
                    $sentAt = $message->sent_at?->copy()->timezone($timezone)->format('d/m/Y H:i');
                    $type = $message->message_type ? str($message->message_type)->replace('_', ' ')->headline() : null;
                    $attachmentsCount = $message->relationLoaded('attachments') ? $message->attachments->count() : 0;
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
                            'bg-white text-gray-950 ring-1 ring-gray-950/10 dark:bg-gray-800 dark:text-gray-100 dark:ring-white/10' => ! $isSent,
                        ])
                    >
                        <div @class([
                            'mb-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs',
                            'text-primary-100' => $isSent,
                            'text-gray-500 dark:text-gray-400' => ! $isSent,
                        ])>
                            <span class="font-medium">
                                {{ $isSent ? 'Você' : 'Cliente' }}
                            </span>

                            @if ($sentAt)
                                <span>{{ $sentAt }}</span>
                            @endif
                        </div>

                        @if ($text !== '')
                            <div class="whitespace-pre-wrap break-words text-sm leading-6">
                                {{ $text }}
                            </div>
                        @else
                            <div @class([
                                'text-sm italic',
                                'text-primary-100' => $isSent,
                                'text-gray-500 dark:text-gray-400' => ! $isSent,
                            ])>
                                Mensagem sem texto.
                            </div>
                        @endif

                        @if ($attachmentsCount > 0)
                            <div @class([
                                'mt-2 text-xs',
                                'text-primary-100' => $isSent,
                                'text-gray-500 dark:text-gray-400' => ! $isSent,
                            ])>
                                {{ trans_choice('{1} :count anexo|[2,*] :count anexos', $attachmentsCount, ['count' => $attachmentsCount]) }}
                            </div>
                        @endif

                        <div @class([
                            'mt-2 flex flex-wrap items-center gap-2 text-xs',
                            'text-primary-100' => $isSent,
                            'text-gray-500 dark:text-gray-400' => ! $isSent,
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
