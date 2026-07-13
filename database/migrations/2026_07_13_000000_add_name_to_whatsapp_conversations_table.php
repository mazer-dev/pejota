<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->string('name')->nullable()->after('phone_number');
        });

        DB::table('whatsapp_conversations')
            ->orderBy('id')
            ->each(function (object $conversation): void {
                $client = $conversation->client_id
                    ? DB::table('clients')->where('id', $conversation->client_id)->first()
                    : null;

                $name = collect([
                    $conversation->push_name,
                    $client?->name,
                    $client?->tradename,
                    $conversation->phone_number,
                    $conversation->remote_jid,
                ])->first(fn ($value): bool => is_string($value) && trim($value) !== '');

                DB::table('whatsapp_conversations')
                    ->where('id', $conversation->id)
                    ->update(['name' => trim((string) $name)]);
            });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->index(['company_id', 'name'], 'wa_conversations_company_name');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropIndex('wa_conversations_company_name');
            $table->dropColumn('name');
        });
    }
};
