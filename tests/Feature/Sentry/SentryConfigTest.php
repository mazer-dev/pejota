<?php

namespace Tests\Feature\Sentry;

use Tests\TestCase;

class SentryConfigTest extends TestCase
{
    public function test_sentry_log_channel_is_registered(): void
    {
        $channel = config('logging.channels.sentry');

        $this->assertIsArray($channel);
        $this->assertSame('sentry', $channel['driver']);
        $this->assertSame('warning', $channel['level']);
    }

    public function test_sentry_config_resolves(): void
    {
        $this->assertIsArray(config('sentry'));
        $this->assertArrayHasKey('dsn', config('sentry'));
    }
}
