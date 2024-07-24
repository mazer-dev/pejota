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
        Schema::create('units', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('description')->nullable();
            $table->string('symbol');
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('service');
            $table->boolean('digital');
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->bigInteger('cost')->default(0);
            $table->bigInteger('price')->default(0);

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
    }
};
