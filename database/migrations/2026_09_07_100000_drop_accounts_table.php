<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `accounts` table never had a consumer: `App\Models\Account` was referenced
 * only by its own Filament resource (`AccountResource`, now removed) and that
 * resource's four pages (`CreateAccount`, `EditAccount`, `ListAccounts`,
 * `ViewAccount`). No other core model related to it.
 *
 * The only foreign key that ever pointed at this table (`payments.account_id`)
 * lived in the closed cloud overlay, not here, and was dropped there first
 * (overlay migration `2026_09_05_100070`) — this migration's timestamp is
 * deliberately later so the drop order is safe when both timelines merge.
 *
 * `initial_balance` was never computed anywhere, and its cast was broken from
 * creation: `Account::casts()` declared the `initial_balance_at` key twice, so
 * the `MoneyCast` landed on the date column and `initial_balance` itself had
 * no cast at all. Given that, and the absence of any consumer, no data is
 * migrated — this drop is destructive by design.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('accounts');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();

            $table->string('name');

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->string('description')->nullable();
            $table->unsignedBigInteger('initial_balance')->default(0);
            $table->date('initial_balance_at')->nullable();

            $table->timestamps();
        });
    }
};
