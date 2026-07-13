<?php

namespace Tests\Feature\Assistant;

use App\Jobs\ProcessAssistantWhatsappMessage;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Ai\AssistantChatService;
use App\Services\Ai\OpenAiAudioTranscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessAssistantWhatsappMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private int $companyId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->companyId = $this->user->company->id;

        config([
            'services.assistant.whatsapp.enabled' => true,
            'services.assistant.whatsapp.instance' => 'Assistente_Pejota',
            'services.evolution.base_url' => 'http://evolution.test',
            'services.evolution.api_key' => 'secret',
        ]);

        Http::fake([
            'http://evolution.test/*' => Http::response(['key' => ['id' => 'SENT1']]),
        ]);
    }

    private function makeSession(): AssistantConversation
    {
        return AssistantConversation::create([
            'company_id' => $this->companyId,
            'user_id' => $this->user->id,
            'title' => 'Atendimento WhatsApp',
            'channel' => AssistantConversation::CHANNEL_WHATSAPP,
            'whatsapp_number' => '5554999371490',
        ]);
    }

    private function addUserMessage(AssistantConversation $session, string $content): AssistantMessage
    {
        return $session->messages()->create([
            'company_id' => $this->companyId,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $content,
        ]);
    }

    private function mockChatService(?string $answer): AssistantChatService
    {
        $service = Mockery::mock(AssistantChatService::class);

        if ($answer === null) {
            $service->shouldNotReceive('respond');
        } else {
            $service->shouldReceive('respond')->once()->andReturn($answer);
        }

        $this->instance(AssistantChatService::class, $service);

        return $service;
    }

    public function test_it_persists_the_answer_and_sends_the_converted_text_to_the_number(): void
    {
        $session = $this->makeSession();
        $message = $this->addUserMessage($session, 'Qual o total do mês?');

        $service = $this->mockChatService("## Total do mês\n\n**R$ 1.000,00** em [3 faturas](https://pejota.test/faturas).");

        (new ProcessAssistantWhatsappMessage($session, $this->user, $message))->handle($service);

        $this->assertDatabaseHas('assistant_messages', [
            'assistant_conversation_id' => $session->id,
            'role' => AssistantMessage::ROLE_ASSISTANT,
            'content' => "## Total do mês\n\n**R$ 1.000,00** em [3 faturas](https://pejota.test/faturas).",
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/message/sendText/Assistente_Pejota')
            && $request['number'] === '5554999371490'
            && $request['text'] === "*Total do mês*\n\n*R$ 1.000,00* em 3 faturas (https://pejota.test/faturas).");
    }

    public function test_it_skips_when_a_newer_user_message_exists(): void
    {
        $session = $this->makeSession();
        $older = $this->addUserMessage($session, 'Primeira da rajada');
        $this->addUserMessage($session, 'Segunda da rajada');

        $service = $this->mockChatService(null);

        (new ProcessAssistantWhatsappMessage($session, $this->user, $older))->handle($service);

        $this->assertSame(0, $session->messages()->where('role', AssistantMessage::ROLE_ASSISTANT)->count());
        Http::assertNothingSent();
    }

    public function test_it_skips_when_the_conversation_was_closed_meanwhile(): void
    {
        $session = $this->makeSession();
        $message = $this->addUserMessage($session, 'Pergunta');

        $session->forceFill(['closed_at' => now()])->save();

        $service = $this->mockChatService(null);

        (new ProcessAssistantWhatsappMessage($session, $this->user, $message))->handle($service);

        $this->assertSame(0, $session->messages()->where('role', AssistantMessage::ROLE_ASSISTANT)->count());
        Http::assertNothingSent();
    }

    public function test_it_transcribes_the_audio_updates_the_content_and_deletes_the_temporary_file_before_responding(): void
    {
        $session = $this->makeSession();
        $message = $this->addUserMessage($session, '[Áudio recebido — transcrevendo…]');

        $audioPath = "assistant/{$this->companyId}/{$session->id}/tmp/audio.ogg";
        Storage::disk('local')->put($audioPath, 'fake-ogg-bytes');

        $transcriber = Mockery::mock(OpenAiAudioTranscriber::class);
        $transcriber->shouldReceive('transcribe')->once()->andReturn('Quantas horas trabalhei hoje?');
        $this->instance(OpenAiAudioTranscriber::class, $transcriber);

        $service = Mockery::mock(AssistantChatService::class);
        $service->shouldReceive('respond')
            ->once()
            ->with(Mockery::on(function (AssistantConversation $conversation) use ($message): bool {
                return $conversation->messages()->find($message->id)?->content === 'Quantas horas trabalhei hoje?';
            }))
            ->andReturn('Você trabalhou 5 horas hoje.');
        $this->instance(AssistantChatService::class, $service);

        (new ProcessAssistantWhatsappMessage($session, $this->user, $message, $audioPath))->handle($service);

        $this->assertSame('Quantas horas trabalhei hoje?', $message->refresh()->content);
        Storage::disk('local')->assertMissing($audioPath);

        Http::assertSent(fn ($request) => $request['text'] === 'Você trabalhou 5 horas hoje.');
    }

    public function test_a_failed_transcription_notifies_the_user_and_stops_without_calling_the_ai(): void
    {
        $session = $this->makeSession();
        $message = $this->addUserMessage($session, '[Áudio recebido — transcrevendo…]');

        $audioPath = "assistant/{$this->companyId}/{$session->id}/tmp/audio.ogg";
        Storage::disk('local')->put($audioPath, 'fake-ogg-bytes');

        $transcriber = Mockery::mock(OpenAiAudioTranscriber::class);
        $transcriber->shouldReceive('transcribe')->once()->andThrow(new RuntimeException('API fora do ar'));
        $this->instance(OpenAiAudioTranscriber::class, $transcriber);

        $service = $this->mockChatService(null);

        (new ProcessAssistantWhatsappMessage($session, $this->user, $message, $audioPath))->handle($service);

        $this->assertSame('[Áudio recebido, mas a transcrição falhou.]', $message->refresh()->content);
        Storage::disk('local')->assertMissing($audioPath);

        Http::assertSent(fn ($request) => str_contains((string) $request['text'], 'Não consegui transcrever seu áudio'));
    }

    public function test_a_long_answer_is_sent_in_multiple_chunks(): void
    {
        $session = $this->makeSession();
        $message = $this->addUserMessage($session, 'Detalha tudo');

        $paragraphs = [];
        for ($i = 0; $i < 6; $i++) {
            $paragraphs[] = str_repeat("linha{$i} ", 250);
        }

        $service = $this->mockChatService(implode("\n\n", $paragraphs));

        (new ProcessAssistantWhatsappMessage($session, $this->user, $message))->handle($service);

        $sendCount = 0;
        Http::assertSent(function ($request) use (&$sendCount) {
            if (str_contains($request->url(), '/message/sendText/')) {
                $sendCount++;
                $this->assertLessThanOrEqual(4000, mb_strlen((string) $request['text']));
            }

            return true;
        });

        $this->assertGreaterThan(1, $sendCount);
    }

    public function test_failed_persists_the_fallback_and_warns_the_user_on_whatsapp(): void
    {
        $session = $this->makeSession();
        $message = $this->addUserMessage($session, 'Pergunta');

        (new ProcessAssistantWhatsappMessage($session, $this->user, $message))->failed(new RuntimeException('worker timeout'));

        $this->assertSame(1, $session->messages()->where('role', AssistantMessage::ROLE_ASSISTANT)->count());

        Http::assertSent(fn ($request) => str_contains((string) $request['text'], 'não conseguiu responder'));
    }
}
