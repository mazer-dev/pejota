<?php

namespace Tests\Feature\Filament;

use App\Filament\App\Resources\ClientMcpTokenResource\Pages\CreateClientMcpToken;
use App\Filament\App\Resources\ClientMcpTokenResource\Pages\ListClientMcpTokens;
use App\Filament\App\Resources\ClientMcpTokenResource\Pages\ViewClientMcpToken;
use App\Filament\App\Resources\ClientResource\Pages\ListClients;
use App\Models\Client;
use App\Models\ClientMcpToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientMcpTokenResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->client = Client::create([
            'company_id' => $this->user->company->id,
            'name' => 'Marlon Confiança',
        ]);
    }

    private function createToken(string $name = 'Claude Code', bool $revoked = false): ClientMcpToken
    {
        return ClientMcpToken::create([
            'company_id' => $this->user->company->id,
            'client_id' => $this->client->id,
            'name' => $name,
            'token_hash' => ClientMcpToken::hashToken(ClientMcpToken::generatePlainToken()),
            'revoked_at' => $revoked ? now() : null,
        ]);
    }

    public function test_the_list_shows_the_access_and_how_many_clients_are_exposed(): void
    {
        $token = $this->createToken();

        Livewire::test(ListClientMcpTokens::class)
            ->assertCanSeeTableRecords([$token])
            ->assertSee('Marlon Confiança')
            ->assertSee('1 client is available via MCP, read only.');
    }

    public function test_the_list_says_when_nothing_is_exposed(): void
    {
        Livewire::test(ListClientMcpTokens::class)
            ->assertSee('No client is exposed via MCP right now.');
    }

    public function test_a_revoked_access_does_not_count_as_exposed(): void
    {
        $this->createToken(revoked: true);

        Livewire::test(ListClientMcpTokens::class)
            ->assertSee('No client is exposed via MCP right now.');
    }

    public function test_creating_an_access_stores_only_the_hash_and_shows_the_token_once(): void
    {
        Livewire::test(CreateClientMcpToken::class)
            ->fillForm([
                'client_id' => $this->client->id,
                'name' => 'Claude Code',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $token = ClientMcpToken::query()->firstOrFail();

        $this->assertSame($this->client->id, $token->client_id);
        $this->assertSame((int) $this->user->company->id, (int) $token->company_id);
        $this->assertNull($token->revoked_at);
        $this->assertSame(64, strlen($token->token_hash));

        $plainToken = session('client_mcp_token_plain');
        $this->assertIsString($plainToken);
        $this->assertStringStartsWith('pjm_', $plainToken);
        $this->assertSame($token->token_hash, ClientMcpToken::hashToken($plainToken));

        $this->assertDatabaseMissing('client_mcp_tokens', ['token_hash' => $plainToken]);
    }

    public function test_the_connection_manual_shows_the_token_and_the_snippets(): void
    {
        $token = $this->createToken();
        $plainToken = 'pjm_tokendeexemplo';

        session()->put('client_mcp_token_plain', $plainToken);

        Livewire::test(ViewClientMcpToken::class, ['record' => $token->id])
            ->assertSee($plainToken)
            ->assertSee('claude mcp add --transport http pejota-marlon-confianca')
            ->assertSee(url('/mcp/client'))
            ->assertSee('Read only');
    }

    public function test_the_connection_manual_masks_the_token_when_it_is_not_fresh(): void
    {
        $token = $this->createToken();

        Livewire::test(ViewClientMcpToken::class, ['record' => $token->id])
            ->assertDontSee('pjm_')
            ->assertSee('&lt;SEU_TOKEN&gt;', escape: false);
    }

    public function test_revoking_from_the_manual_blocks_the_access(): void
    {
        $token = $this->createToken();

        Livewire::test(ViewClientMcpToken::class, ['record' => $token->id])
            ->callAction('revoke');

        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_the_client_list_marks_who_is_exposed(): void
    {
        $exposed = $this->client;

        $notExposed = Client::create([
            'company_id' => $this->user->company->id,
            'name' => 'Cliente sem MCP',
        ]);

        $this->createToken();

        Livewire::test(ListClients::class)
            ->assertCanSeeTableRecords([$exposed, $notExposed])
            ->assertTableColumnStateSet('active_mcp_tokens_count', 1, $exposed)
            ->assertTableColumnStateSet('active_mcp_tokens_count', 0, $notExposed);
    }
}
