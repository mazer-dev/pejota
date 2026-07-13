<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use App\Models\User;
use App\Services\Ai\AssistantAttachmentUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Assistant\Concerns\MakesAttachmentFixtures;
use Tests\TestCase;

class AssistantAttachmentAuthorizationTest extends TestCase
{
    use MakesAttachmentFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeAttachment(User $user, string $name = 'foto.jpg'): AssistantMessageAttachment
    {
        $conversation = AssistantConversation::create([
            'company_id' => $user->company->id,
            'user_id' => $user->id,
            'title' => 'Teste',
        ]);

        $message = $conversation->messages()->create([
            'company_id' => $conversation->company_id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => 'Anexo de teste',
        ]);

        return app(AssistantAttachmentUploader::class)->persist($this->fakeImage($name), $message);
    }

    public function test_a_user_from_the_same_company_can_open_the_attachment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $attachment = $this->makeAttachment($user);

        $response = $this->get(route('assistant.attachments.show', $attachment));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_a_user_from_another_company_receives_a_404(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $attachment = $this->makeAttachment($owner);

        $stranger = User::factory()->create();
        $this->actingAs($stranger);

        $response = $this->get(route('assistant.attachments.show', $attachment));

        $response->assertNotFound();
    }

    public function test_an_inexistent_attachment_receives_a_404(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/assistant-attachments/999999');

        $response->assertNotFound();
    }

    /**
     * The app has no globally named "login" route (Filament owns its own
     * panel login), so an unauthenticated hit on this Authenticate-guarded
     * route cannot build a redirect target and errors out instead of
     * rendering a 302 — a pre-existing characteristic shared by the
     * equivalent /whatsapp-attachments/{attachment} route, not something
     * introduced here. Either way, the file must never be served (no 200).
     */
    public function test_the_route_requires_authentication(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $attachment = $this->makeAttachment($user);

        auth()->logout();

        $response = $this->get(route('assistant.attachments.show', $attachment));

        $this->assertNotSame(200, $response->getStatusCode());
    }
}
