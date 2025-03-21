<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounts_payable', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->comment('Document number');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status')->default('pending'); // pending, paid, partial, cancelled
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('financial_categories')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignIdFor(\App\Models\Contract::class, 'contract_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_payable');
    }
};