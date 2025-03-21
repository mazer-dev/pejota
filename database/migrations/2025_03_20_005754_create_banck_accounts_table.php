<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_number')->nullable();
            $table->string('agency')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('type');
            $table->string('currency', 3)->default('BRL');
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->date('initial_balance_date');
            $table->decimal('current_balance', 15, 2)->default(0);

            // For credit cards
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->integer('due_day')->nullable();
            $table->integer('closing_day')->nullable();

            // For loans
            $table->decimal('loan_amount', 15, 2)->nullable();
            $table->decimal('interest_rate', 10, 4)->nullable();
            $table->date('loan_start_date')->nullable();
            $table->date('loan_end_date')->nullable();

            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};