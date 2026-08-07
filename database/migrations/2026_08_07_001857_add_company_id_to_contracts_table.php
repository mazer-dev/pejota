<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dá tenancy a `contracts`, que não tinha nenhuma: nem `company_id`, nem
     * `BelongsToTenants`, nem escopo no `ContractResource`. Com um tenant em
     * produção o efeito era nulo; num produto vendido, é vazamento entre
     * clientes.
     *
     * POR QUE A COLUNA, e não um escopo derivado do cliente: `client_id` é
     * nullable desde `2024_07_12_220805_create_vendors_table.php:32-34`, no mesmo
     * bloco que acrescentou `vendor_id`. Um contrato tem quatro formas possíveis
     * — só cliente, só fornecedor, ambos, nenhum — e derivar a empresa do cliente
     * faria o contrato de FORNECEDOR sumir para todo mundo. Dado desaparecendo é
     * pior que dado vazando.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->restrictOnDelete();
        });

        // Subconsulta correlacionada, e não `UPDATE ... JOIN`: o JOIN em UPDATE
        // não existe no SQLite, que é o banco da suíte.
        DB::update(
            'update contracts set company_id = (select company_id from clients where clients.id = contracts.client_id)
             where client_id is not null'
        );

        DB::update(
            'update contracts set company_id = (select company_id from vendors where vendors.id = contracts.vendor_id)
             where company_id is null and vendor_id is not null'
        );

        $orfaos = DB::table('contracts')->whereNull('company_id')->count();

        if ($orfaos > 0) {
            throw new RuntimeException(
                "Migration interrompida: {$orfaos} contrato(s) sem cliente e sem fornecedor, logo sem empresa derivável. "
                .'Atribua-os manualmente (update contracts set company_id = ? where id in (...)) e rode a migration de novo. '
                .'Falhar aqui é deliberado: inventar a empresa colocaria contrato na empresa errada, e deixar nulo o '
                .'tornaria invisível para todos os tenants depois que o escopo entrar.'
            );
        }

        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
