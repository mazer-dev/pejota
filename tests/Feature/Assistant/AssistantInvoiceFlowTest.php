<?php

namespace Tests\Feature\Assistant;

use App\Enums\InvoiceStatusEnum;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ai\AiCliRunner;
use App\Services\Ai\AssistantChatService;
use App\Services\Ai\AssistantInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AssistantInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $companyId = $this->user->company->id;

        $this->client = Client::create([
            'company_id' => $companyId,
            'name' => 'Felipe França',
        ]);

        $this->unit = Unit::create([
            'name' => 'Hora',
            'symbol' => 'h',
            'company_id' => $companyId,
        ]);

        $this->product = Product::create([
            'name' => 'Desenvolvimento',
            'service' => true,
            'digital' => false,
            'price' => 90.00,
            'unit_id' => $this->unit->id,
            'company_id' => $companyId,
        ]);
    }

    private function makeConversation(string $question): AssistantConversation
    {
        $conversation = AssistantConversation::create([
            'company_id' => $this->user->company->id,
            'user_id' => $this->user->id,
            'title' => $question,
        ]);

        $conversation->messages()->create([
            'company_id' => $this->user->company->id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $question,
        ]);

        return $conversation;
    }

    /**
     * @return array<string, mixed>
     */
    private function draftPayload(): array
    {
        return [
            'client_id' => $this->client->id,
            'title' => 'Desenvolvimento julho',
            'due_date' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'name' => 'Horas de desenvolvimento',
                    'quantity' => 20,
                    'price_cents' => 9000,
                    'product_id' => $this->product->id,
                    'unit_id' => $this->unit->id,
                ],
            ],
        ];
    }

    private function storePending(AssistantConversation $conversation, string $passphrase, ?string $expiresAt = null): void
    {
        $service = app(AssistantInvoiceService::class);
        [$draft, $errors] = $service->validateDraft($this->draftPayload(), (int) $conversation->company_id);
        $this->assertSame([], $errors);

        $conversation->forceFill([
            'pending_action' => [
                'type' => 'create_invoice',
                'draft' => $draft,
                'passphrase' => $passphrase,
                'expires_at' => $expiresAt ?? now()->addMinutes(15)->toISOString(),
            ],
        ])->save();
    }

    public function test_a_valid_draft_stores_a_pending_action_and_asks_for_the_passphrase(): void
    {
        $conversation = $this->makeConversation('Cria uma fatura de 20h pro Felipe, vence semana que vem');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->andReturn(json_encode(['invoice' => $this->draftPayload()]));
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $pending = $conversation->fresh()->pending_action;

        $this->assertSame('create_invoice', $pending['type']);
        $this->assertNotEmpty($pending['passphrase']);
        $this->assertStringContainsString('Felipe França', $answer);
        $this->assertStringContainsString('1.800,00', $answer);
        $this->assertStringContainsString('digite exatamente', $answer);
        $this->assertStringContainsString($pending['passphrase'], $answer);
        $this->assertSame(0, Invoice::allTenants()->count());
    }

    public function test_typing_the_exact_passphrase_creates_the_invoice_without_calling_the_ai(): void
    {
        $conversation = $this->makeConversation('Girassol');
        $this->storePending($conversation, 'Girassol');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldNotReceive('complete');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $invoice = Invoice::allTenants()->firstOrFail();

        $this->assertSame(InvoiceStatusEnum::SENT, InvoiceStatusEnum::from($invoice->status instanceof InvoiceStatusEnum ? $invoice->status->value : $invoice->status));
        $this->assertSame('Desenvolvimento julho', $invoice->title);
        $this->assertSame($this->client->id, $invoice->client_id);
        $this->assertSame(now()->addDays(7)->toDateString(), $invoice->due_date?->toDateString());
        $this->assertEqualsWithDelta(1800.00, (float) $invoice->total, 0.001);
        $this->assertSame(1, $invoice->items()->count());
        $this->assertNull($conversation->fresh()->pending_action);
        $this->assertStringContainsString('criada', $answer);
        $this->assertStringContainsString($invoice->number, $answer);
    }

    public function test_a_passphrase_with_wrong_case_does_not_create_the_invoice(): void
    {
        $conversation = $this->makeConversation('girassol');
        $this->storePending($conversation, 'Girassol');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->andReturn('{"say": "A palavra não confere. Digite exatamente a palavra mostrada."}');
        $this->instance(AiCliRunner::class, $runner);

        app(AssistantChatService::class)->respond($conversation);

        $this->assertSame(0, Invoice::allTenants()->count());
        $this->assertNotNull($conversation->fresh()->pending_action);
    }

    public function test_an_expired_pending_draft_is_discarded_and_creates_nothing(): void
    {
        $conversation = $this->makeConversation('Girassol');
        $this->storePending($conversation, 'Girassol', now()->subMinute()->toISOString());

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->andReturn('{"say": "O rascunho expirou, vamos montar de novo?"}');
        $this->instance(AiCliRunner::class, $runner);

        app(AssistantChatService::class)->respond($conversation);

        $this->assertSame(0, Invoice::allTenants()->count());
        $this->assertNull($conversation->fresh()->pending_action);
    }

    public function test_a_draft_without_due_date_is_rejected_with_feedback(): void
    {
        $conversation = $this->makeConversation('Cria uma fatura de 20h pro Felipe');

        $draft = $this->draftPayload();
        unset($draft['due_date']);

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn(json_encode(['invoice' => $draft]));
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Rascunho de fatura rejeitado')
                && str_contains($prompt, 'due_date')))
            ->andReturn('{"say": "Qual a data de vencimento da fatura?"}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Qual a data de vencimento da fatura?', $answer);
        $this->assertNull($conversation->fresh()->pending_action);
        $this->assertSame(0, Invoice::allTenants()->count());
    }

    public function test_the_assistant_cannot_update_or_delete_invoices(): void
    {
        $existing = Invoice::create([
            'company_id' => $this->user->company->id,
            'number' => 'INV-1',
            'title' => 'Fatura existente',
            'client_id' => $this->client->id,
            'status' => InvoiceStatusEnum::SENT,
            'total' => 500.00,
        ]);

        $conversation = $this->makeConversation('Apaga a fatura INV-1 e zera o total');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"query": "UPDATE invoices SET total = 0 WHERE number = \'INV-1\'"}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Consulta rejeitada')))
            ->andReturn('{"query": "DELETE FROM invoices"}');
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Consulta rejeitada')))
            ->andReturn('{"say": "Não consigo alterar nem excluir faturas, só criar com sua confirmação."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertStringContainsString('Não consigo alterar', $answer);

        $existing->refresh();
        $this->assertEqualsWithDelta(500.00, (float) $existing->total, 0.001);
        $this->assertSame(1, Invoice::allTenants()->count());
    }

    public function test_invented_write_actions_like_invoice_delete_are_ignored(): void
    {
        $existing = Invoice::create([
            'company_id' => $this->user->company->id,
            'number' => 'INV-1',
            'title' => 'Fatura existente',
            'client_id' => $this->client->id,
            'status' => InvoiceStatusEnum::SENT,
            'total' => 500.00,
        ]);

        $conversation = $this->makeConversation('Exclui a fatura INV-1');

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn(json_encode(['invoice_delete' => ['number' => 'INV-1']]));
        $runner->shouldReceive('complete')
            ->once()
            ->ordered()
            ->andReturn('{"say": "Só posso criar faturas, nunca excluir."}');
        $this->instance(AiCliRunner::class, $runner);

        $answer = app(AssistantChatService::class)->respond($conversation);

        $this->assertSame('Só posso criar faturas, nunca excluir.', $answer);
        $this->assertSame(1, Invoice::allTenants()->count());
        $this->assertEqualsWithDelta(500.00, (float) $existing->fresh()->total, 0.001);
    }
}
