<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O fornecedor da assinatura, que até aqui só existia como texto livre dentro de
 * `service`. `nullOnDelete` porque a assinatura sobrevive ao cadastro do fornecedor:
 * a descrição continua identificando o compromisso, mesmo sem o vínculo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreignId('vendor_id')
                ->nullable()
                ->after('service')
                ->constrained('vendors')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vendor_id');
        });
    }
};
