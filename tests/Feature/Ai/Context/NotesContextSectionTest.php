<?php

namespace Tests\Feature\Ai\Context;

use App\Models\Client;
use App\Models\Note;
use App\Models\User;
use App\Services\Ai\Context\NotesContextSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesContextSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_without_client_or_project(): void
    {
        $this->assertNull((new NotesContextSection)->build());
    }

    public function test_it_lists_recent_notes_with_excerpt_extracted_from_builder_blocks(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $companyId = $user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        Note::create([
            'title' => 'Reunião inicial',
            'content' => [
                ['type' => 'text', 'data' => ['content' => 'Cliente confirmou escopo do projeto.']],
            ],
            'company_id' => $companyId,
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        $context = (new NotesContextSection)->build($client);

        $this->assertNotNull($context);
        $this->assertStringContainsString('Últimas notas:', $context);
        $this->assertStringContainsString('Reunião inicial', $context);
        $this->assertStringContainsString('Cliente confirmou escopo do projeto.', $context);
    }

    public function test_it_returns_null_when_no_notes_exist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Sem notas']);

        $this->assertNull((new NotesContextSection)->build($client));
    }
}
