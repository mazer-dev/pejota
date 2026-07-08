<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Models\WhatsappAttachment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\CliWhatsappMessageSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CliWhatsappMessageSuggesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_client_project_messages_and_processed_attachments_to_ai_cli(): void
    {
        $user = User::factory()->create();
        $companyId = $user->company->id;
        $this->actingAs($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(function (string $prompt): bool {
                return str_contains($prompt, 'Veio da 99freelas')
                    && str_contains($prompt, 'Confirmar escopo antes de prometer prazo')
                    && str_contains($prompt, 'Transcrição: preciso confirmar os e-mails')
                    && str_contains($prompt, 'PDF: existem 19 e-mails')
                    && str_contains($prompt, 'Imagem: tela de assinatura do Claude Max')
                    && str_contains($prompt, 'rascunho atual')
                    && str_contains($prompt, 'Retorne somente o texto da mensagem');
            }))
            ->andReturn('Claro, vou conferir isso e te retorno com a confirmação.');

        $this->instance(AiCliRunner::class, $runner);

        $client = Client::create([
            'company_id' => $companyId,
            'name' => 'Vivianne',
            'phone' => '5511999990000',
            'ai_context' => 'Veio da 99freelas e prefere mensagens objetivas.',
        ]);

        $project = Project::create([
            'company_id' => $companyId,
            'client_id' => $client->id,
            'name' => 'Configuração de E-mails no HubSpot',
            'description' => 'Projeto para ajustar cadências.',
            'ai_context' => 'Confirmar escopo antes de prometer prazo.',
        ]);

        $conversation = WhatsappConversation::create([
            'company_id' => $companyId,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'evolution_instance' => 'client_instance',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);

        $message = WhatsappMessage::create([
            'company_id' => $companyId,
            'whatsapp_conversation_id' => $conversation->id,
            'client_id' => $client->id,
            'project_id' => $project->id,
            'evolution_instance' => 'client_instance',
            'remote_message_id' => 'MSG1',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'sender_name' => 'Vivianne',
            'from_me' => false,
            'message_type' => 'audio',
            'text' => null,
            'sent_at' => now(),
        ]);

        WhatsappAttachment::create([
            'company_id' => $companyId,
            'whatsapp_message_id' => $message->id,
            'mime_type' => 'audio/ogg',
            'transcription_text' => 'Transcrição: preciso confirmar os e-mails de boas-vindas.',
            'status' => 'stored',
        ]);

        WhatsappAttachment::create([
            'company_id' => $companyId,
            'whatsapp_message_id' => $message->id,
            'original_filename' => 'briefing.pdf',
            'mime_type' => 'application/pdf',
            'extracted_text' => 'PDF: existem 19 e-mails na cadência.',
            'status' => 'stored',
        ]);

        WhatsappAttachment::create([
            'company_id' => $companyId,
            'whatsapp_message_id' => $message->id,
            'original_filename' => 'screenshot.png',
            'mime_type' => 'image/png',
            'extracted_text' => 'Imagem: tela de assinatura do Claude Max.',
            'status' => 'stored',
        ]);

        $suggestion = app(CliWhatsappMessageSuggester::class)->suggest($conversation, 'rascunho atual');

        $this->assertSame('Claro, vou conferir isso e te retorno com a confirmação.', $suggestion);
    }
}
