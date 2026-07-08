<?php

namespace Tests\Feature\Assistant;

use App\Enums\InvoiceStatusEnum;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\Ai\AssistantQuickAnswers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantQuickAnswersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_today_chip_lists_overdue_and_due_today_tasks_only(): void
    {
        $companyId = $this->user->company->id;

        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);
        $status = Status::create([
            'name' => 'A Fazer', 'phase' => 'todo', 'color' => '#000', 'sort_order' => 1, 'active' => true, 'company_id' => $companyId,
        ]);

        Task::create([
            'title' => 'Tarefa atrasada', 'status_id' => $status->id, 'company_id' => $companyId,
            'client_id' => $client->id, 'due_date' => now()->subDays(2)->toDateString(),
        ]);
        Task::create([
            'title' => 'Tarefa de hoje', 'status_id' => $status->id, 'company_id' => $companyId,
            'due_date' => now()->toDateString(),
        ]);
        Task::create([
            'title' => 'Tarefa futura', 'status_id' => $status->id, 'company_id' => $companyId,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $answer = app(AssistantQuickAnswers::class)->answer(AssistantQuickAnswers::CHIP_TODAY);

        $this->assertStringContainsString('Tarefa atrasada', $answer);
        $this->assertStringContainsString('[Vivianne]', $answer);
        $this->assertStringContainsString('Tarefa de hoje', $answer);
        $this->assertStringNotContainsString('Tarefa futura', $answer);
    }

    public function test_today_chip_reports_empty_state(): void
    {
        $answer = app(AssistantQuickAnswers::class)->answer(AssistantQuickAnswers::CHIP_TODAY);

        $this->assertStringContainsString('No open tasks', $answer);
    }

    public function test_overdue_invoices_chip_lists_only_overdue_pending_invoices(): void
    {
        $companyId = $this->user->company->id;
        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        Invoice::create([
            'number' => 'INV-1', 'title' => 'Vencida', 'client_id' => $client->id, 'company_id' => $companyId,
            'due_date' => now()->subDays(3)->toDateString(), 'total' => 900, 'status' => InvoiceStatusEnum::SENT->value,
        ]);
        Invoice::create([
            'number' => 'INV-2', 'title' => 'Futura', 'client_id' => $client->id, 'company_id' => $companyId,
            'due_date' => now()->addDays(3)->toDateString(), 'total' => 100, 'status' => InvoiceStatusEnum::SENT->value,
        ]);
        Invoice::create([
            'number' => 'INV-3', 'title' => 'Paga', 'client_id' => $client->id, 'company_id' => $companyId,
            'due_date' => now()->subDays(3)->toDateString(), 'total' => 100, 'status' => InvoiceStatusEnum::PAID->value,
            'payment_date' => now()->toDateString(),
        ]);

        $answer = app(AssistantQuickAnswers::class)->answer(AssistantQuickAnswers::CHIP_OVERDUE_INVOICES);

        $this->assertStringContainsString('INV-1', $answer);
        $this->assertStringNotContainsString('INV-2', $answer);
        $this->assertStringNotContainsString('INV-3', $answer);
    }

    public function test_week_summary_chip_totals_recent_sessions_by_client(): void
    {
        $companyId = $this->user->company->id;
        $client = Client::create(['company_id' => $companyId, 'name' => 'Vivianne']);

        WorkSession::create([
            'title' => 'Recente', 'client_id' => $client->id, 'company_id' => $companyId, 'user_id' => $this->user->id,
            'start' => now()->subDay(), 'end' => now()->subDay()->addHours(2),
        ]);
        WorkSession::create([
            'title' => 'Antiga', 'client_id' => $client->id, 'company_id' => $companyId, 'user_id' => $this->user->id,
            'start' => now()->subDays(30), 'end' => now()->subDays(30)->addHour(),
        ]);

        $answer = app(AssistantQuickAnswers::class)->answer(AssistantQuickAnswers::CHIP_WEEK_SUMMARY);

        $this->assertStringContainsString('1 session', $answer);
        $this->assertStringContainsString('Vivianne: 02h00', $answer);
    }

    public function test_unknown_chip_returns_null(): void
    {
        $this->assertNull(app(AssistantQuickAnswers::class)->answer('nope'));
    }
}
