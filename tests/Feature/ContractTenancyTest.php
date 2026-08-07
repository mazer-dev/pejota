<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

/**
 * `Contract` não tinha recorte por empresa nenhum — nem no model, nem no
 * `ContractResource`. Com um tenant em produção o efeito era nulo; num produto
 * vendido, é vazamento entre clientes.
 *
 * Estes testes cobrem os QUATRO caminhos por onde um contrato é alcançado hoje,
 * porque o escopo tem de valer em todos: consulta direta ao model e as relações
 * declaradas em `Client:36`, `Vendor:34` e `Project:36`.
 */
class ContractTenancyTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    /** @return array{0: Company, 1: Contract} */
    private function contractInItsOwnCompany(string $name): array
    {
        $company = $this->actingInCompany(User::factory()->create());
        $client = Client::create(['name' => $name, 'company_id' => $company->id]);

        $contract = Contract::create([
            'title' => 'Contrato '.$name,
            'content' => 'conteúdo',
            'start_at' => '2026-01-01',
            'signatures' => [],
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        return [$company, $contract];
    }

    public function test_a_contract_does_not_leak_to_another_company(): void
    {
        [, $alheio] = $this->contractInItsOwnCompany('Alfa');
        [, $meu] = $this->contractInItsOwnCompany('Beta');

        $visiveis = Contract::query()->pluck('id');

        $this->assertTrue($visiveis->contains($meu->id));
        $this->assertFalse(
            $visiveis->contains($alheio->id),
            'contrato de outra empresa apareceu na consulta direta ao model'
        );
    }

    public function test_the_company_is_stamped_on_create_without_being_passed(): void
    {
        $company = $this->actingInCompany(User::factory()->create());
        $client = Client::create(['name' => 'Gama', 'company_id' => $company->id]);

        $contract = Contract::create([
            'title' => 'sem company_id explícito',
            'content' => 'conteúdo',
            'start_at' => '2026-01-01',
            'signatures' => [],
            'client_id' => $client->id,
        ]);

        $this->assertSame($company->id, $contract->refresh()->company_id);
    }

    public function test_the_client_relation_does_not_leak_either(): void
    {
        [$outra, $alheio] = $this->contractInItsOwnCompany('Delta');
        $clienteAlheio = Client::withoutGlobalScopes()->find($alheio->client_id);

        $this->actingInCompany(User::factory()->create());

        $this->assertCount(
            0,
            $clienteAlheio->contracts()->get(),
            'a relação Client::contracts() devolveu contrato de outra empresa'
        );
        $this->assertNotNull($outra);
    }

    public function test_the_vendor_relation_does_not_leak_either(): void
    {
        $outraEmpresa = $this->actingInCompany(User::factory()->create());
        $vendorAlheio = Vendor::create(['name' => 'Fornecedor alheio', 'company_id' => $outraEmpresa->id]);
        $clienteAlheio = Client::create(['name' => 'Epsilon', 'company_id' => $outraEmpresa->id]);
        Contract::create([
            'title' => 'de fornecedor',
            'content' => 'conteúdo',
            'start_at' => '2026-01-01',
            'signatures' => [],
            'client_id' => $clienteAlheio->id,
            'vendor_id' => $vendorAlheio->id,
            'company_id' => $outraEmpresa->id,
        ]);

        $this->actingInCompany(User::factory()->create());

        $this->assertCount(
            0,
            $vendorAlheio->contracts()->get(),
            'a relação Vendor::contracts() devolveu contrato de outra empresa'
        );
    }

    /**
     * O "falhar alto" da migration é decisão do sponsor de 2026-08-06, e sem este
     * teste seria a única regra da mudança que ninguém verifica. O caminho é
     * incomum de propósito: desfaz a coluna, planta um contrato sem cliente e sem
     * fornecedor por baixo do model (para o `BelongsToTenants` não carimbar nada),
     * e roda a migration de novo.
     */
    public function test_the_migration_refuses_to_guess_a_company_for_an_orphan_contract(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_id');
        });

        DB::table('contracts')->insert([
            'title' => 'órfão',
            'content' => 'sem cliente e sem fornecedor',
            'start_at' => '2026-01-01',
            'signatures' => '[]',
            'client_id' => null,
            'vendor_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/1 contrato\(s\) sem cliente e sem fornecedor/');

        (require database_path('migrations/2026_08_07_001857_add_company_id_to_contracts_table.php'))->up();
    }

    public function test_the_project_relation_does_not_leak_either(): void
    {
        $outraEmpresa = $this->actingInCompany(User::factory()->create());
        $clienteAlheio = Client::create(['name' => 'Zeta', 'company_id' => $outraEmpresa->id]);
        $projetoAlheio = Project::create([
            'name' => 'Projeto alheio',
            'client_id' => $clienteAlheio->id,
            'company_id' => $outraEmpresa->id,
        ]);
        Contract::create([
            'title' => 'de projeto',
            'content' => 'conteúdo',
            'start_at' => '2026-01-01',
            'signatures' => [],
            'client_id' => $clienteAlheio->id,
            'project_id' => $projetoAlheio->id,
            'company_id' => $outraEmpresa->id,
        ]);

        $this->actingInCompany(User::factory()->create());

        $this->assertCount(
            0,
            $projetoAlheio->contracts()->get(),
            'a relação Project::contracts() devolveu contrato de outra empresa'
        );
    }
}
