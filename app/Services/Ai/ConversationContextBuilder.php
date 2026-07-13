<?php

namespace App\Services\Ai;

use App\Models\Client;
use App\Models\Project;
use App\Models\WhatsappConversation;

class ConversationContextBuilder
{
    public function build(
        ?Client $client = null,
        ?Project $project = null,
        ?string $conversationContext = null,
        ?WhatsappConversation $conversation = null,
    ): string {
        $client ??= $project?->client;

        return collect([
            $this->conversationPersonSection($conversation),
            $this->clientSection($client),
            $this->projectSection($project),
            $this->section('Histórico completo armazenado desta conversa', $conversationContext),
        ])
            ->filter()
            ->implode("\n\n");
    }

    private function conversationPersonSection(?WhatsappConversation $conversation): ?string
    {
        if (! $conversation) {
            return null;
        }

        return $this->section('Pessoa desta conversa (não confundir com o cadastro do cliente)', $this->joinLines([
            $this->line('Nome definido manualmente', $conversation->name),
            $this->line('Telefone desta pessoa', $conversation->phone_number),
            $this->line('Nome remoto informado pelo WhatsApp', $conversation->push_name),
        ]));
    }

    private function clientSection(?Client $client): ?string
    {
        if (! $client) {
            return null;
        }

        $lines = [
            $this->line('Nome do cliente', $client->name),
            $this->line('Nome fantasia', $client->tradename),
            $this->line('Email', $client->email),
            $this->line('Telefone', $client->phone),
            $this->section('Contexto de conversa do cliente', $client->ai_context),
        ];

        return $this->section('Cliente', $this->joinLines($lines));
    }

    private function projectSection(?Project $project): ?string
    {
        if (! $project) {
            return null;
        }

        $lines = [
            $this->line('Nome do projeto', $project->name),
            $this->section('Descricao do projeto', $this->cleanRichText($project->description)),
            $this->section('Contexto do projeto', $project->ai_context),
        ];

        return $this->section('Projeto', $this->joinLines($lines));
    }

    private function line(string $label, mixed $value): ?string
    {
        $value = $this->cleanText($value);

        return $value === null ? null : "{$label}: {$value}";
    }

    private function section(string $title, mixed $content): ?string
    {
        $content = $this->cleanText($content);

        return $content === null ? null : "{$title}:\n{$content}";
    }

    private function joinLines(array $lines): ?string
    {
        $content = collect($lines)
            ->filter()
            ->implode("\n");

        return $content === '' ? null : $content;
    }

    private function cleanRichText(mixed $value): ?string
    {
        return $this->cleanText(strip_tags(html_entity_decode((string) $value)));
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(preg_replace('/[ \t]+/', ' ', (string) $value) ?? '');
        $text = trim(preg_replace('/\R{3,}/', "\n\n", $text) ?? '');

        return $text === '' ? null : $text;
    }
}
