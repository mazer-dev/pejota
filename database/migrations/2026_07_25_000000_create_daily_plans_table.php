<?php

use App\Enums\DailyPlanModeEnum;
use App\Enums\DailyPlanStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->date('plan_date');
            $table->string('mode')->default(DailyPlanModeEnum::FULL->value);
            $table->string('status')->default(DailyPlanStatusEnum::GENERATING->value);
            $table->unsignedInteger('capacity_minutes')->default(0);
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->text('summary')->nullable();
            $table->json('warnings')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'plan_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plans');
    }
};
