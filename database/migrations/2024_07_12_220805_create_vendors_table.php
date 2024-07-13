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
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('tradename')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->timestamps();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Vendor::class, 'vendor_id')
                ->nullable();

            $table->foreignIdFor(\App\Models\Client::class, 'client_id')
                ->nullable()
                ->change();

            $table->bigInteger('total')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
