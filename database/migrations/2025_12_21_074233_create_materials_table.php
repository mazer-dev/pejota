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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            // The unique SKU (Stock Keeping Unit) for the material
            $table->string('sku')->unique();
            $table->string('name');
            // Unit of measurement (e.g., kg, m3, ton, bag)
            $table->string('unit');
            // Prices: 15 total digits, 2 after the decimal point (e.g., 999,999,999,999.99)
            $table->decimal('cost_price', 15, 2)->nullable(); 
            $table->decimal('sale_price', 15, 2)->nullable(); 
            $table->decimal('reorder_point', 12, 2)->default(0); // Minimum qty before alert
            $table->string('barcode')->nullable()->unique(); // For QR/Barcode support      

            // VAT Rate: Stored as a percentage (e.g., 15 for 15%)
            $table->integer('vat_rate')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
