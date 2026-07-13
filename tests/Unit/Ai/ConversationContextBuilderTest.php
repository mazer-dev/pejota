<?php

namespace Tests\Unit\Ai;

use App\Models\Client;
use App\Models\Project;
use App\Services\Ai\ConversationContextBuilder;
use Tests\TestCase;

class ConversationContextBuilderTest extends TestCase
{
    public function test_it_builds_context_from_client_project_and_recent_conversation(): void
    {
        $client = new Client([
            'name' => 'Cliente Exemplo',
            'tradename' => 'Exemplo LTDA',
            'email' => 'cliente@example.com',
            'phone' => '+55 11 99999-9999',
            'ai_context' => 'Veio da 99freelas e prefere combinados objetivos pelo WhatsApp.',
        ]);

        $project = new Project([
            'name' => 'Sistema de propostas',
            'description' => '<p>Escopo inicial: CRM com automacoes.</p>',
            'ai_context' => 'Priorizar entrega por etapas e confirmar prazo antes de prometer.',
        ]);

        $project->setRelation('client', $client);

        $context = (new ConversationContextBuilder)->build(
            project: $project,
            conversationContext: 'Cliente enviou audio perguntando sobre a proxima entrega.'
        );

        $this->assertStringContainsString('Nome do cliente: Cliente Exemplo', $context);
        $this->assertStringContainsString('Contexto de conversa do cliente:', $context);
        $this->assertStringContainsString('Veio da 99freelas', $context);
        $this->assertStringContainsString('Nome do projeto: Sistema de propostas', $context);
        $this->assertStringContainsString('Escopo inicial: CRM com automacoes.', $context);
        $this->assertStringContainsString('Contexto do projeto:', $context);
        $this->assertStringContainsString('Histórico completo armazenado desta conversa:', $context);
        $this->assertStringNotContainsString('<p>', $context);
    }
}
