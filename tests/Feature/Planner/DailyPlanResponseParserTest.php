<?php

namespace Tests\Feature\Planner;

use App\Exceptions\DailyPlanParseException;
use App\Services\Planner\DailyPlanContext;
use App\Services\Planner\DailyPlanResponseParser;
use Tests\TestCase;

class DailyPlanResponseParserTest extends TestCase
{
    private function parser(): DailyPlanResponseParser
    {
        return app(DailyPlanResponseParser::class);
    }

    private function context(int $capacityMinutes = 480): DailyPlanContext
    {
        return new DailyPlanContext(
            text: 'ctx',
            capacityMinutes: $capacityMinutes,
            validTaskIds: [10, 11],
            validInvoiceIds: [20],
            validContractIds: [30],
            validClientIds: [40, 41],
            validConversationIds: [50],
        );
    }

    public function test_parses_clean_json_and_json_wrapped_in_fences_or_prose(): void
    {
        $payload = '{"summary": "Foco no dia.", "items": [{"type": "task", "title": "Fazer X", "estimated_minutes": 60, "reason": "vence hoje", "task_id": 10}], "warnings": []}';

        foreach ([$payload, "```json\n{$payload}\n```", "Segue o plano:\n{$payload}\nBom dia!"] as $response) {
            $parsed = $this->parser()->parse($response, $this->context());

            $this->assertSame('Foco no dia.', $parsed->summary);
            $this->assertCount(1, $parsed->items);
            $this->assertSame(10, $parsed->items[0]['task_id']);
            $this->assertSame(60, $parsed->items[0]['estimated_minutes']);
        }
    }

    public function test_ids_outside_the_whitelist_are_stripped_or_drop_the_item(): void
    {
        $response = json_encode([
            'summary' => 'ok',
            'items' => [
                ['type' => 'task', 'title' => 'Tarefa alucinada', 'estimated_minutes' => 30, 'task_id' => 999],
                ['type' => 'follow_up', 'title' => 'Cobrar cliente', 'estimated_minutes' => 10, 'client_id' => 999, 'whatsapp_conversation_id' => 50],
            ],
        ]);

        $parsed = $this->parser()->parse($response, $this->context());

        $this->assertCount(1, $parsed->items);
        $this->assertSame('follow_up', $parsed->items[0]['type']);
        $this->assertNull($parsed->items[0]['client_id']);
        $this->assertSame(50, $parsed->items[0]['whatsapp_conversation_id']);
    }

    public function test_estimated_minutes_are_clamped(): void
    {
        $response = json_encode([
            'summary' => 'ok',
            'items' => [
                ['type' => 'task', 'title' => 'Muito curta', 'estimated_minutes' => 1, 'task_id' => 10],
                ['type' => 'admin', 'title' => 'Absurda', 'estimated_minutes' => 8000],
            ],
        ]);

        $parsed = $this->parser()->parse($response, $this->context(capacityMinutes: 2000));

        $this->assertSame(5, $parsed->items[0]['estimated_minutes']);
        $this->assertSame(480, $parsed->items[1]['estimated_minutes']);
    }

    public function test_items_beyond_capacity_are_cut_with_a_warning(): void
    {
        $response = json_encode([
            'summary' => 'ok',
            'items' => [
                ['type' => 'task', 'title' => 'A', 'estimated_minutes' => 200, 'task_id' => 10],
                ['type' => 'task', 'title' => 'B', 'estimated_minutes' => 200, 'task_id' => 11],
                ['type' => 'invoice', 'title' => 'C', 'estimated_minutes' => 200, 'invoice_id' => 20],
            ],
        ]);

        $parsed = $this->parser()->parse($response, $this->context(capacityMinutes: 480));

        $this->assertCount(2, $parsed->items);
        $this->assertNotEmpty($parsed->warnings);
    }

    public function test_follow_ups_and_habits_get_capacity_grace(): void
    {
        $response = json_encode([
            'summary' => 'ok',
            'items' => [
                ['type' => 'task', 'title' => 'A', 'estimated_minutes' => 470, 'task_id' => 10],
                ['type' => 'follow_up', 'title' => 'Cobrar', 'estimated_minutes' => 15, 'client_id' => 40],
            ],
        ]);

        $parsed = $this->parser()->parse($response, $this->context(capacityMinutes: 480));

        $this->assertCount(2, $parsed->items);
    }

    public function test_only_one_follow_up_per_client_is_kept(): void
    {
        $response = json_encode([
            'summary' => 'ok',
            'items' => [
                ['type' => 'follow_up', 'title' => 'Cobrar A', 'estimated_minutes' => 10, 'client_id' => 40],
                ['type' => 'follow_up', 'title' => 'Cobrar A de novo', 'estimated_minutes' => 10, 'client_id' => 40],
                ['type' => 'follow_up', 'title' => 'Cobrar B', 'estimated_minutes' => 10, 'client_id' => 41],
            ],
        ]);

        $parsed = $this->parser()->parse($response, $this->context());

        $this->assertCount(2, $parsed->items);
    }

    public function test_light_mode_with_zero_capacity_keeps_all_items(): void
    {
        $response = json_encode([
            'summary' => 'ok',
            'items' => [
                ['type' => 'invoice', 'title' => 'Urgente', 'estimated_minutes' => 30, 'invoice_id' => 20],
            ],
        ]);

        $parsed = $this->parser()->parse($response, $this->context(capacityMinutes: 0));

        $this->assertCount(1, $parsed->items);
    }

    public function test_unusable_response_throws(): void
    {
        $this->expectException(DailyPlanParseException::class);

        $this->parser()->parse('não consegui montar o plano', $this->context());
    }
}
