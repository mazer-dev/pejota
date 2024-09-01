<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->string('title');
            $table->longText('extra_info')->nullable();
            $table->longText('obs_internal')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignIdFor(\App\Models\Client::class, 'client_id');
            $table->foreignIdFor(\App\Models\Project::class, 'project_id')->nullable();
            $table->foreignIdFor(\App\Models\Contract::class, 'contract_id')->nullable();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->timestamps();

            $table->decimal('total', 10, 2);
            $table->decimal('discount', 10, 2)->nullable();

            $table->string('status')->default(\App\Enums\InvoiceStatusEnum::DRAFT->value);

            $table->unique(['company_id', 'number']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
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
        Schema::dropIfExists('invoice_product');
        Schema::dropIfExists('invoices');
    }
};
