<?php

namespace App\Console\Commands;

use App\Jobs\SyncWhatsappConversationHistory;
use App\Models\Company;
use App\Models\WhatsappConversation;
use Illuminate\Console\Command;

class SyncRecentWhatsappConversations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pj:whatsapp-sync-recent
        {--company= : Restrict to a single company ID}
        {--days= : Sync conversations active in the last N days (defaults to the planner window)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza o histórico das conversas de WhatsApp recentes antes da geração do plano do dia.';

    public function handle(): int
    {
        if (blank(config('services.evolution.api_key'))) {
            $this->warn('Evolution API não configurada, nada a sincronizar.');

            return self::SUCCESS;
        }

        $days = filled($this->option('days'))
            ? max(1, (int) $this->option('days'))
            : max(1, (int) config('services.planner.conversation_window_days', 14));

        $maxConversations = max(1, (int) config('services.planner.sync_max_conversations', 30));

        $companies = filled($this->option('company'))
            ? Company::query()->where('id', $this->option('company'))->get()
            : Company::all();

        foreach ($companies as $company) {
            $conversations = WhatsappConversation::allTenants()
                ->where('company_id', $company->id)
                ->whereNotNull('last_message_at')
                ->where('last_message_at', '>=', now()->subDays($days))
                ->orderByDesc('last_message_at')
                ->take($maxConversations)
                ->get();

            /**
             * Dispatches are staggered so a batch of syncs never hammers
             * the Evolution API; the job itself is ShouldBeUnique, so
             * re-dispatching an in-flight conversation is a no-op.
             */
            foreach ($conversations->values() as $index => $conversation) {
                SyncWhatsappConversationHistory::dispatch($conversation, $company->user_id)
                    ->delay(now()->addSeconds($index * 20));
            }

            $this->line("{$company->name}: {$conversations->count()} conversa(s) enviadas para sincronização.");
        }

        return self::SUCCESS;
    }
}
