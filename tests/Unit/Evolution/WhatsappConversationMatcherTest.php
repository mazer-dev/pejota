<?php

namespace Tests\Unit\Evolution;

use App\Services\Evolution\WhatsappConversationMatcher;
use Tests\TestCase;

class WhatsappConversationMatcherTest extends TestCase
{
    public function test_it_scores_whatsapp_numbers_with_country_code_variations(): void
    {
        $matcher = new WhatsappConversationMatcher;

        $this->assertSame(100, $matcher->score('5511999990000', '+55 (11) 99999-0000'));
        $this->assertGreaterThanOrEqual(88, $matcher->score('5511999990000', '11999990000'));
        $this->assertGreaterThanOrEqual(80, $matcher->score('5511999990000', '999990000'));
        $this->assertLessThan(70, $matcher->score('5511999990000', '5581888881111'));
    }
}
