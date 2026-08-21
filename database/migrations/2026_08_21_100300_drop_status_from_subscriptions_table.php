<?php

use App\Enums\SubscriptionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O estado passa a ser derivado de `canceled_at` e `trial_ends_at`. A coluna era uma
 * segunda verdade sobre o mesmo fato, sem nada mantendo as duas de acordo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('status')->default(SubscriptionStatusEnum::TRIAL->value);
        });
    }
};
