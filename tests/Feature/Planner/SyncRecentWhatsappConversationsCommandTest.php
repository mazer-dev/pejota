<?php

namespace Tests\Feature\Planner;

use App\Jobs\SyncWhatsappConversationHistory;
use App\Models\User;
use App\Models\WhatsappConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SyncRecentWhatsappConversationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.evolution.api_key' => 'secret']);
    }

    private function makeConversation(int $companyId, string $jid, ?\DateTimeInterface $lastMessageAt): WhatsappConversation
    {
        return WhatsappConversation::create([
            'company_id' => $companyId,
            'evolution_instance' => 'inst',
            'remote_jid' => $jid,
            'status' => 'open',
            'last_message_at' => $lastMessageAt,
        ]);
    }

    public function test_dispatches_sync_only_for_recent_conversations(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $companyId = $user->company->id;

        $recent = $this->makeConversation($companyId, 'recent@s.whatsapp.net', now()->subDays(2));
        $stale = $this->makeConversation($companyId, 'stale@s.whatsapp.net', now()->subDays(60));
        $this->makeConversation($companyId, 'never@s.whatsapp.net', null);

        $this->artisan('pj:whatsapp-sync-recent', ['--company' => $companyId])
            ->expectsOutputToContain('1 conversa(s)')
            ->assertSuccessful();

        Bus::assertDispatched(SyncWhatsappConversationHistory::class, function (SyncWhatsappConversationHistory $job) use ($recent): bool {
            return $job->conversation->id === $recent->id;
        });
        Bus::assertNotDispatched(SyncWhatsappConversationHistory::class, function (SyncWhatsappConversationHistory $job) use ($stale): bool {
            return $job->conversation->id === $stale->id;
        });
    }

    public function test_respects_the_max_conversations_cap(): void
    {
        Bus::fake();

        config(['services.planner.sync_max_conversations' => 2]);

        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->makeConversation($user->company->id, "c{$i}@s.whatsapp.net", now()->subHours($i));
        }

        $this->artisan('pj:whatsapp-sync-recent', ['--company' => $user->company->id])
            ->expectsOutputToContain('2 conversa(s)')
            ->assertSuccessful();

        Bus::assertDispatchedTimes(SyncWhatsappConversationHistory::class, 2);
    }

    public function test_does_nothing_when_evolution_is_not_configured(): void
    {
        Bus::fake();

        config(['services.evolution.api_key' => null]);

        User::factory()->create();

        $this->artisan('pj:whatsapp-sync-recent')
            ->expectsOutputToContain('não configurada')
            ->assertSuccessful();

        Bus::assertNotDispatched(SyncWhatsappConversationHistory::class);
    }
}
