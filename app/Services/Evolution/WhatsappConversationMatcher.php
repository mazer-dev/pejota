<?php

namespace App\Services\Evolution;

use App\Models\Client;
use App\Models\WhatsappConversation;

class WhatsappConversationMatcher
{
    public function linkClient(Client $client): ?WhatsappConversation
    {
        $conversation = $this->bestConversationForClient($client);
        if (! $conversation) {
            return null;
        }

        $conversation->forceFill([
            'client_id' => $client->id,
        ])->save();

        return $conversation;
    }

    public function linkConversation(WhatsappConversation $conversation): ?Client
    {
        $client = $this->bestClientForConversation($conversation);
        if (! $client) {
            return null;
        }

        $conversation->forceFill([
            'client_id' => $client->id,
        ])->save();

        return $client;
    }

    public function bestConversationForClient(Client $client): ?WhatsappConversation
    {
        $target = $this->digits($client->phone);
        if ($target === '') {
            return null;
        }

        $match = WhatsappConversation::allTenants()
            ->where('company_id', $client->company_id)
            ->get()
            ->map(fn (WhatsappConversation $conversation): array => [
                'conversation' => $conversation,
                'score' => $this->score($target, $this->digits($conversation->phone_number ?: $conversation->remote_jid)),
            ])
            ->filter(fn (array $item): bool => $item['score'] >= 70)
            ->sortByDesc('score')
            ->first();

        return $match['conversation'] ?? null;
    }

    public function bestClientForConversation(WhatsappConversation $conversation): ?Client
    {
        $target = $this->digits($conversation->phone_number ?: $conversation->remote_jid);
        if ($target === '') {
            return null;
        }

        $match = Client::allTenants()
            ->where('company_id', $conversation->company_id)
            ->get()
            ->map(fn (Client $client): array => [
                'client' => $client,
                'score' => $this->score($target, $this->digits($client->phone)),
            ])
            ->filter(fn (array $item): bool => $item['score'] >= 70)
            ->sortByDesc('score')
            ->first();

        return $match['client'] ?? null;
    }

    public function score(string $left, string $right): int
    {
        $left = $this->digits($left);
        $right = $this->digits($right);

        if ($left === '' || $right === '') {
            return 0;
        }

        if ($left === $right) {
            return 100;
        }

        if (str_ends_with($left, $right) || str_ends_with($right, $left)) {
            return 92;
        }

        if (substr($left, -9) === substr($right, -9)) {
            return 88;
        }

        if (substr($left, -8) === substr($right, -8)) {
            return 80;
        }

        similar_text($left, $right, $percent);

        return (int) round($percent);
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
