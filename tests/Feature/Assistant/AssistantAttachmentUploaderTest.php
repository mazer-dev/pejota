<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Models\User;
use App\Services\Ai\AssistantAttachmentUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Feature\Assistant\Concerns\MakesAttachmentFixtures;
use Tests\TestCase;

class AssistantAttachmentUploaderTest extends TestCase
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

    private function makeMessage(): AssistantMessage
    {
        $conversation = AssistantConversation::create([
            'company_id' => $this->user->company->id,
            'user_id' => $this->user->id,
            'title' => 'Teste',
        ]);

        return $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => 'Anexos de teste',
        ]);
    }

    public function test_assert_valid_rejects_files_over_the_configured_size_limit(): void
    {
        config(['services.assistant.attachments.max_file_mb' => 1]);

        $file = UploadedFile::fake()->create('grande.jpg', 2 * 1024, 'image/jpeg');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('excede o limite de 1 MB');

        app(AssistantAttachmentUploader::class)->assertValid($file);
    }

    public function test_assert_valid_rejects_a_disallowed_extension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo de arquivo não permitido');

        app(AssistantAttachmentUploader::class)->assertValid($this->fakeDisallowedExtension());
    }

    public function test_assert_valid_rejects_a_mime_that_does_not_match_the_extension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não corresponde ao tipo esperado');

        app(AssistantAttachmentUploader::class)->assertValid($this->fakeAdulteratedImage());
    }

    public function test_assert_valid_accepts_a_real_image_pdf_docx_xlsx_csv_and_txt(): void
    {
        $uploader = app(AssistantAttachmentUploader::class);

        $uploader->assertValid($this->fakeImage());
        $uploader->assertValid($this->fakePdf());
        $uploader->assertValid($this->fakeDocx());
        $uploader->assertValid($this->fakeXlsx());
        $uploader->assertValid($this->fakeCsv());
        $uploader->assertValid($this->fakeTxt());

        $this->addToAssertionCount(6);
    }

    public function test_assert_batch_is_valid_rejects_more_than_the_configured_max_files(): void
    {
        config(['services.assistant.attachments.max_files' => 2]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no máximo 2 arquivos');

        app(AssistantAttachmentUploader::class)->assertBatchIsValid([
            $this->fakeImage('a.jpg'),
            $this->fakeImage('b.jpg'),
            $this->fakeImage('c.jpg'),
        ]);
    }

    public function test_assert_batch_is_valid_rejects_everything_when_attachments_are_disabled(): void
    {
        config(['services.assistant.attachments.enabled' => false]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('desabilitado');

        app(AssistantAttachmentUploader::class)->assertBatchIsValid([$this->fakeImage()]);
    }

    public function test_persist_stores_the_file_under_the_expected_private_path_and_creates_the_record(): void
    {
        $message = $this->makeMessage();
        $file = $this->fakePdf('Contrato Assinado.pdf');

        $attachment = app(AssistantAttachmentUploader::class)->persist($file, $message);

        $this->assertInstanceOf(AssistantMessageAttachment::class, $attachment);
        $this->assertSame($message->company_id, $attachment->company_id);
        $this->assertSame($message->id, $attachment->assistant_message_id);
        $this->assertSame('Contrato Assinado.pdf', $attachment->original_filename);
        $this->assertSame('pdf', $attachment->extension);
        $this->assertSame(AssistantMessageAttachment::STATUS_STORED, $attachment->status);
        $this->assertNotNull($attachment->sha256);

        $expectedPrefix = "assistant/{$message->company_id}/{$message->assistant_conversation_id}/{$message->id}/";
        $this->assertStringStartsWith($expectedPrefix, $attachment->path);
        $this->assertStringEndsWith('.pdf', $attachment->path);
        $this->assertNotSame('Contrato Assinado.pdf', basename($attachment->path));

        Storage::disk('local')->assertExists($attachment->path);
    }
}
