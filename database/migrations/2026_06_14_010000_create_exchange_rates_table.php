<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('currency_code', 3);
            $table->date('date');
            $table->decimal('rate', 20, 10);
            $table->string('source')->default('manual');
            $table->boolean('is_frozen')->default(false);
            $table->timestamps();

            $table->unique(['currency_code', 'date']);
            $table->foreign('currency_code')->references('code')->on('currencies');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
