<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncWhatsappConversationHistory;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Services\Evolution\WhatsappConversationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SyncWhatsappConversationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_unique_job_notifies_completion(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversation($user);

        $sync = Mockery::mock(WhatsappConversationSyncService::class);
        $sync->shouldReceive('syncAll')->once()->with(Mockery::on(
            fn (WhatsappConversation $record): bool => $record->is($conversation),
        ))->andReturn(42);

        $job = new SyncWhatsappConversationHistory($conversation, $user->id);
        $this->assertSame((string) $conversation->id, $job->uniqueId());

        $job->handle($sync);

        $notification = DatabaseNotification::where('notifiable_id', $user->id)->sole();
        $this->assertStringContainsString('Histórico do WhatsApp sincronizado', $notification->data['title']);
        $this->assertStringContainsString('42 mensagens', $notification->data['body']);
    }

    public function test_a_partial_failure_is_reported_and_can_be_retried_idempotently(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversation($user);
        $job = new SyncWhatsappConversationHistory($conversation, $user->id);

        $job->failed(new RuntimeException('Evolution indisponível na página 3.'));

        $notification = DatabaseNotification::where('notifiable_id', $user->id)->sole();
        $this->assertStringContainsString('Falha ao sincronizar', $notification->data['title']);
        $this->assertStringContainsString('página 3', $notification->data['body']);
        $this->assertStringContainsString('repetida com segurança', $notification->data['body']);
    }

    private function conversation(User $user): WhatsappConversation
    {
        return WhatsappConversation::create([
            'company_id' => $user->company->id,
            'name' => 'Karen',
            'evolution_instance' => 'inst',
            'remote_jid' => '5511999990000@s.whatsapp.net',
            'phone_number' => '5511999990000',
            'status' => 'open',
        ]);
    }
}
