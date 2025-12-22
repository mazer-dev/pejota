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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();

            // Link to the Header
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            
            // Material & Quantity
            $table->foreignId('material_id')->constrained(); 
            $table->decimal('qty', 12, 2);
            $table->decimal('unit_price', 15, 2);
            
            // We don't need a total column here if we calculate it in the Model/UI, 
            // but many ERPs store it for faster reporting.
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
