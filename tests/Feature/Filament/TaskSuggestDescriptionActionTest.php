<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\TaskResource\Pages\CreateTask;
use App\Models\Client;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class TaskSuggestDescriptionActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_the_description_field_with_the_ai_suggestion(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $client = Client::create(['company_id' => $user->company->id, 'name' => 'Vivianne']);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')->once()->andReturn('Configurar o módulo de cobranças recorrentes.');
        $this->instance(AiCliRunner::class, $runner);

        Livewire::test(CreateTask::class)
            ->fillForm([
                'title' => 'Configurar cobranças',
                'client' => $client->id,
            ])
            ->callFormComponentAction('description', 'suggestDescription')
            ->assertFormSet([
                'description' => '<p>Configurar o módulo de cobranças recorrentes.</p>',
            ]);
    }

    public function test_it_warns_and_skips_the_ai_call_when_title_is_blank(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldNotReceive('complete');
        $this->instance(AiCliRunner::class, $runner);

        Livewire::test(CreateTask::class)
            ->callFormComponentAction('description', 'suggestDescription')
            ->assertNotified();
    }
}
