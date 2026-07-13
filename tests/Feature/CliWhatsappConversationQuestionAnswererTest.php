<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\CliWhatsappConversationQuestionAnswerer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CliWhatsappConversationQuestionAnswererTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_distinguishes_the_employee_from_the_client_and_uses_the_full_history(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Empresa ACME']);
        $project = Project::create(['company_id' => $user->company->id, 'client_id' => $client->id, 'name' => 'Portal ACME']);
        $conversation = WhatsappConversation::create([
            'company_id' => $user->company->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'name' => 'João — Financeiro',
            'push_name' => 'João remoto',
            'evolution_instance' => 'inst',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        foreach (range(1, 35) as $number) {
            WhatsappMessage::create([
                'company_id' => $user->company->id,
                'whatsapp_conversation_id' => $conversation->id,
                'client_id' => $client->id,
                'project_id' => $project->id,
                'evolution_instance' => 'inst',
                'remote_message_id' => "Q{$number}",
                'from_me' => false,
                'message_type' => 'text',
                'text' => $number === 1 ? 'MARCADOR DA MENSAGEM MAIS ANTIGA' : "Mensagem {$number}",
                'sent_at' => now()->addSeconds($number),
            ]);
        }

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->with(Mockery::on(function (string $prompt): bool {
            return str_contains($prompt, 'Nome definido manualmente: João — Financeiro')
                && str_contains($prompt, 'Nome do cliente: Empresa ACME')
                && str_contains($prompt, 'Nome do projeto: Portal ACME')
                && str_contains($prompt, 'MARCADOR DA MENSAGEM MAIS ANTIGA')
                && str_contains($prompt, 'Quando a informação pedida não estiver disponível');
        }))->andReturn('Não encontrei essa informação nos dados vinculados.');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(CliWhatsappConversationQuestionAnswerer::class)
            ->answer($conversation, 'Qual é a data do próximo boleto?');

        $this->assertSame('Não encontrei essa informação nos dados vinculados.', $answer);
    }
}
