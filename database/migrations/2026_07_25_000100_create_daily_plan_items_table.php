<?php

use App\Enums\DailyPlanItemStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('daily_plan_id')->constrained('daily_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type');
            $table->string('title');
            $table->text('reason')->nullable();
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->string('status')->default(DailyPlanItemStatusEnum::PENDING->value);
            $table->timestamp('done_at')->nullable();
            $table->text('suggested_message')->nullable();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('whatsapp_conversation_id')->nullable()->constrained('whatsapp_conversations')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'daily_plan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_items');
    }
};
