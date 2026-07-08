<?php

namespace Tests\Unit\Evolution;

use App\Services\Ai\OpenAiTokenCounter;
use Tests\TestCase;

class OpenAiTokenCounterTest extends TestCase
{
    public function test_it_counts_non_empty_context_text(): void
    {
        $counter = new OpenAiTokenCounter;

        $tokens = $counter->count('Cliente veio da 99freelas. Combinamos entrega semanal pelo WhatsApp.');

        $this->assertGreaterThan(0, $tokens);
    }
}
