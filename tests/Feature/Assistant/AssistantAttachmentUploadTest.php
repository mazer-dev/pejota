<?php

namespace Tests\Feature\Assistant;

use App\Jobs\ProcessAssistantMessage;
use App\Livewire\AssistantChat;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Assistant\Concerns\MakesAttachmentFixtures;
use Tests\TestCase;

class AssistantAttachmentUploadTest extends TestCase
{
    use MakesAttachmentFixtures, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_sending_text_only_creates_no_attachments(): void
    {
        Bus::fake();

        Livewire::test(AssistantChat::class)
            ->set('message', 'Quantas tarefas eu tenho?')
            ->call('send');

        $message = AssistantMessage::where('role', AssistantMessage::ROLE_USER)->sole();
        $this->assertSame('Quantas tarefas eu tenho?', $message->content);
        $this->assertCount(0, $message->attachments);
    }

    public function test_sending_a_single_attachment_without_text_uses_the_default_instruction(): void
    {
        Bus::fake();

        Livewire::test(AssistantChat::class)
            ->set('attachments', [$this->fakeImage('foto.jpg')])
            ->call('send')
            ->assertSet('attachments', []);

        $message = AssistantMessage::where('role', AssistantMessage::ROLE_USER)->sole();
        $this->assertSame('Analise os anexos e apresente um resumo dos pontos principais.', $message->content);
        $this->assertCount(1, $message->attachments);

        $attachment = $message->attachments->first();
        $this->assertSame('foto.jpg', $attachment->original_filename);
        $this->assertSame(AssistantMessageAttachment::STATUS_STORED, $attachment->status);
        $this->assertNotNull($attachment->path);
        Storage::disk('local')->assertExists($attachment->path);

        Bus::assertDispatched(ProcessAssistantMessage::class);
    }

    public function test_sending_text_with_one_two_or_three_attachments(): void
    {
        Bus::fake();

        Livewire::test(AssistantChat::class)
            ->set('message', 'Compare estes arquivos')
            ->set('attachments', [
                $this->fakeImage('foto.jpg'),
                $this->fakePdf('contrato.pdf'),
                $this->fakeTxt('notas.txt'),
            ])
            ->call('send');

        $message = AssistantMessage::where('role', AssistantMessage::ROLE_USER)->sole();
        $this->assertSame('Compare estes arquivos', $message->content);
        $this->assertCount(3, $message->attachments);
        $this->assertSame(
            ['foto.jpg', 'contrato.pdf', 'notas.txt'],
            $message->attachments->pluck('original_filename')->all(),
        );
    }

    public function test_a_fourth_attachment_is_rejected_and_previous_three_are_kept(): void
    {
        Livewire::test(AssistantChat::class)
            ->set('attachments', [
                $this->fakeImage('a.jpg'),
                $this->fakePdf('b.pdf'),
                $this->fakeTxt('c.txt'),
                $this->fakeTxt('d.txt'),
            ])
            ->assertSet('attachments', function (array $attachments): bool {
                return count($attachments) === 3;
            })
            ->assertSet('attachmentError', 'Você pode enviar no máximo 3 arquivos por mensagem.');
    }

    /**
     * config/livewire.php keeps Livewire's own temporary-upload transport
     * cap in sync with services.assistant.attachments.max_file_mb, so a
     * file this far over the limit never reaches our component code at
     * all — Livewire's own upload endpoint rejects it first and surfaces
     * the failure as a validation error on the property itself. That is
     * still an end-to-end rejection of the file, which is what matters
     * here; AssistantAttachmentUploaderTest covers our own size-limit
     * message directly, at the service level.
     */
    public function test_an_oversized_attachment_is_rejected(): void
    {
        Livewire::test(AssistantChat::class)
            ->set('attachments', [$this->fakeOversizedImage()])
            ->assertHasErrors('attachments.0');
    }

    public function test_an_adulterated_mime_type_is_rejected_despite_the_allowed_extension(): void
    {
        Livewire::test(AssistantChat::class)
            ->set('attachments', [$this->fakeAdulteratedImage()])
            ->assertSet('attachments', [])
            ->assertSet('attachmentError', function (string $error): bool {
                return str_contains($error, 'não corresponde ao tipo esperado');
            });
    }

    public function test_a_disallowed_extension_is_rejected(): void
    {
        Livewire::test(AssistantChat::class)
            ->set('attachments', [$this->fakeDisallowedExtension()])
            ->assertSet('attachments', [])
            ->assertSet('attachmentError', function (string $error): bool {
                return str_contains($error, 'Tipo de arquivo não permitido');
            });
    }

    public function test_an_attachment_can_be_removed_before_sending(): void
    {
        Bus::fake();

        Livewire::test(AssistantChat::class)
            ->set('attachments', [$this->fakeImage('a.jpg'), $this->fakePdf('b.pdf')])
            ->call('removeAttachment', 0)
            ->assertSet('attachments', function (array $attachments): bool {
                return count($attachments) === 1;
            })
            ->set('message', 'Só o PDF')
            ->call('send');

        $message = AssistantMessage::where('role', AssistantMessage::ROLE_USER)->sole();
        $this->assertCount(1, $message->attachments);
        $this->assertSame('b.pdf', $message->attachments->first()->original_filename);
    }

    public function test_typed_text_survives_adding_and_removing_attachments(): void
    {
        Livewire::test(AssistantChat::class)
            ->set('message', 'Meu texto original')
            ->set('attachments', [$this->fakeImage('a.jpg')])
            ->assertSet('message', 'Meu texto original')
            ->call('removeAttachment', 0)
            ->assertSet('message', 'Meu texto original');
    }

    public function test_starting_a_conversation_with_only_attachments_titles_it_after_the_first_filename(): void
    {
        Bus::fake();

        Livewire::test(AssistantChat::class)
            ->set('attachments', [$this->fakePdf('Proposta Comercial.pdf')])
            ->call('send');

        $conversation = AssistantConversation::first();
        $this->assertNotNull($conversation);
        $this->assertSame('Proposta Comercial.pdf', $conversation->title);
    }

    public function test_new_conversation_clears_pending_attachments(): void
    {
        Livewire::test(AssistantChat::class)
            ->set('attachments', [$this->fakeImage('a.jpg')])
            ->call('newConversation')
            ->assertSet('attachments', []);
    }
}
