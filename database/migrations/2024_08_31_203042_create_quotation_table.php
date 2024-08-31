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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->string('title');
            $table->longText('obs')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignIdFor(Client::class, 'client_id');
            $table->foreignIdFor(Project::class, 'project_id')->nullable();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();
            $table->timestamps();

            $table->decimal('total', 10, 2);
            $table->decimal('discount', 10, 2)->nullable();

            $table->unique(['company_id', 'number']);
        });

        Schema::create('product_quotation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name');

            $table->foreignIdFor(\App\Models\Unit::class)
                ->constrained('units')
                ->restrictOnDelete();

            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->string('obs')->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_quotation');
        Schema::dropIfExists('quotations');
    }
};
