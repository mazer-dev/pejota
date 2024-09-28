<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->string('service');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('payment_method')->default('credit_card');
            $table->string('payment_info')->nullable();
            $table->date('canceled_at')->nullable();
            $table->string('billing_period')->default(\App\Enums\SubscriptionBillingPeriodEnum::MONTHLY->value);
            $table->date('trial_ends_at')->nullable();
            $table->string('status')->default(\App\Enums\SubscriptionStatusEnum::TRIAL->value);
            $table->text('obs')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
