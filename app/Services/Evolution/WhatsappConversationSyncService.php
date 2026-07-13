<?php

namespace App\Services\Evolution;

use App\Models\WhatsappConversation;
use Illuminate\Support\Collection;

class WhatsappConversationSyncService
{
    public function __construct(
        private readonly EvolutionApiClient $client,
        private readonly EvolutionWebhookHandler $handler,
        private readonly WhatsappJidNormalizer $normalizer,
    ) {}

    public function sync(WhatsappConversation $conversation, int $limit = 50, bool $discoverCandidates = true, bool $withMedia = true): int
    {
        $conversation->loadMissing('client');

        $seen = [];
        $candidateGroups = [$this->candidates($conversation, false)];
        if ($discoverCandidates) {
            $candidateGroups[] = fn (): array => $this->candidates($conversation, true);
        }

        foreach ($candidateGroups as $group) {
            $group = is_callable($group) ? $group() : $group;

            foreach ($group as $candidate) {
                if (isset($seen[$candidate['remote_jid']])) {
                    continue;
                }
                $seen[$candidate['remote_jid']] = true;

                $page = $this->client->findMessagesPage(
                    $conversation->evolution_instance,
                    $candidate['remote_jid'],
                    page: 1,
                    offset: $limit,
                );
                $records = $page['records'];

                if ($records === []) {
                    continue;
                }

                $this->applyCandidate($conversation, $candidate);

                $records = collect($records)
                    ->filter(fn ($record) => is_array($record))
                    ->sortBy(fn (array $record): int => $this->messageTimestamp($record))
                    ->values()
                    ->all();

                return $this->handler->handle([
                    'event' => 'MESSAGES_UPSERT',
                    'instance' => $conversation->evolution_instance,
                    'sender' => $candidate['remote_jid'],
                    'date_time' => now()->toISOString(),
                    'data' => $records,
                ], dispatchSuggestions: false, withMedia: $withMedia);
            }
        }

        return 0;
    }

    /**
     * Imports every page in the background. Historical media is kept as
     * metadata only: no file download or AI enrichment occurs during a
     * backfill. Message uniqueness makes the operation idempotent.
     */
    public function syncAll(WhatsappConversation $conversation, int $offset = 100): int
    {
        $conversation->loadMissing('client');

        $seen = [];
        $candidateGroups = [
            $this->candidates($conversation, false),
            fn (): array => $this->candidates($conversation, true),
        ];

        foreach ($candidateGroups as $group) {
            $group = is_callable($group) ? $group() : $group;

            foreach ($group as $candidate) {
                if (isset($seen[$candidate['remote_jid']])) {
                    continue;
                }
                $seen[$candidate['remote_jid']] = true;

                $pageNumber = 1;
                $imported = 0;
                $matched = false;

                do {
                    $page = $this->client->findMessagesPage(
                        $conversation->evolution_instance,
                        $candidate['remote_jid'],
                        page: $pageNumber,
                        offset: $offset,
                    );

                    if ($page['records'] === []) {
                        break;
                    }

                    if (! $matched) {
                        $this->applyCandidate($conversation, $candidate);
                        $matched = true;
                    }

                    $records = collect($page['records'])
                        ->sortBy(fn (array $record): int => $this->messageTimestamp($record))
                        ->values()
                        ->all();

                    $imported += $this->handler->handle([
                        'event' => 'MESSAGES_UPSERT',
                        'instance' => $conversation->evolution_instance,
                        'sender' => $candidate['remote_jid'],
                        'date_time' => now()->toISOString(),
                        'data' => $records,
                    ], dispatchSuggestions: false, withMedia: false, refreshTokens: false);

                    $pageNumber++;
                } while ($pageNumber <= $page['pages']);

                if ($matched) {
                    app(WhatsappConversationTokenService::class)->refresh($conversation->fresh());

                    return $imported;
                }
            }
        }

        app(WhatsappConversationTokenService::class)->refresh($conversation->fresh());

        return 0;
    }

    /**
     * @return array<int, array{remote_jid: string, score: int, phone_number: ?string, push_name: ?string}>
     */
    private function candidates(WhatsappConversation $conversation, bool $discoverCandidates = true): array
    {
        $candidates = collect();

        if ($conversation->remote_jid) {
            $candidates->push([
                'remote_jid' => $conversation->remote_jid,
                'score' => 1000,
                'phone_number' => $this->phoneFromJid($conversation->remote_jid),
                'push_name' => $conversation->push_name,
            ]);
        }

        if (! $discoverCandidates) {
            return $candidates->values()->all();
        }

        $rows = [
            ...$this->client->findChats($conversation->evolution_instance),
            ...$this->client->findContacts($conversation->evolution_instance),
        ];

        foreach ($rows as $row) {
            foreach ($this->candidateJids($row) as $jid) {
                $score = $this->score($conversation, $row, $jid);
                if ($score < 80) {
                    continue;
                }

                $candidates->push([
                    'remote_jid' => $jid,
                    'score' => $score,
                    'phone_number' => $this->candidatePhone($row, $jid),
                    'push_name' => $this->candidateName($row),
                ]);
            }
        }

        return $candidates
            ->filter(fn (array $candidate): bool => $candidate['remote_jid'] !== '')
            ->groupBy('remote_jid')
            ->map(fn (Collection $items): array => $items->sortByDesc('score')->first())
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function candidateJids(array $row): array
    {
        return collect([
            data_get($row, 'remoteJid'),
            data_get($row, 'key.remoteJid'),
            data_get($row, 'lastMessage.key.remoteJid'),
            data_get($row, 'key.remoteJidAlt'),
            data_get($row, 'lastMessage.key.remoteJidAlt'),
        ])
            ->filter(fn ($jid): bool => is_string($jid) && trim($jid) !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function score(WhatsappConversation $conversation, array $row, string $jid): int
    {
        $score = 0;
        $targetNumbers = collect([$conversation->phone_number, $conversation->remote_jid])
            ->filter(fn ($value): bool => is_string($value) && ! str_contains($value, '@lid'))
            ->flatMap(fn (string $value): array => $this->normalizer->candidates($value))
            ->unique();
        $candidateNumbers = collect([
            $jid,
            data_get($row, 'remoteJid'),
            data_get($row, 'key.remoteJidAlt'),
            data_get($row, 'lastMessage.key.remoteJidAlt'),
        ])
            ->filter(fn ($value): bool => is_string($value) && ! str_contains($value, '@lid'))
            ->flatMap(fn (string $value): array => $this->normalizer->candidates($value))
            ->unique();

        if ($targetNumbers->intersect($candidateNumbers)->isNotEmpty()) {
            $score = 140;
        }

        $targetNames = collect([
            $conversation->push_name,
            $conversation->client?->name,
            $conversation->client?->tradename,
        ])
            ->map(fn ($name): string => $this->normaliseName($name))
            ->filter();

        $candidateName = $this->normaliseName($this->candidateName($row));
        if ($score > 0 && $candidateName !== '') {
            foreach ($targetNames as $name) {
                if ($name !== '' && (str_contains($candidateName, $name) || str_contains($name, $candidateName))) {
                    $score += 60;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * The manual name is deliberately absent here. Discovery can update only
     * remote addressing and remote WhatsApp metadata.
     */
    private function applyCandidate(WhatsappConversation $conversation, array $candidate): void
    {
        $conversation->forceFill([
            'remote_jid' => $candidate['remote_jid'],
            'phone_number' => $candidate['phone_number'] ?: $conversation->phone_number,
            'push_name' => $conversation->push_name ?: $candidate['push_name'],
        ])->save();
    }

    private function candidateName(array $row): ?string
    {
        $name = data_get($row, 'pushName') ?: data_get($row, 'lastMessage.pushName') ?: data_get($row, 'senderName');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private function candidatePhone(array $row, string $jid): ?string
    {
        foreach ([data_get($row, 'lastMessage.key.remoteJidAlt'), data_get($row, 'key.remoteJidAlt'), $jid, data_get($row, 'remoteJid')] as $candidate) {
            $phone = $this->phoneFromJid($candidate);
            if ($phone) {
                return $phone;
            }
        }

        return null;
    }

    private function phoneFromJid(mixed $jid): ?string
    {
        if (! is_string($jid) || str_contains($jid, '@lid')) {
            return null;
        }

        $digits = $this->digits(str($jid)->before('@')->toString());

        return $digits === '' ? null : $digits;
    }

    private function digits(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?: '';
    }

    private function normaliseName(mixed $value): string
    {
        return str((string) $value)
            ->lower()
            ->ascii()
            ->squish()
            ->toString();
    }

    private function messageTimestamp(array $record): int
    {
        $timestamp = data_get($record, 'messageTimestamp');

        if (! is_numeric($timestamp)) {
            return 0;
        }

        $timestamp = (int) $timestamp;

        return $timestamp > 9999999999 ? (int) floor($timestamp / 1000) : $timestamp;
    }
}
