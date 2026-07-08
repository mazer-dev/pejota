<?php

namespace App\Services\Evolution;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EvolutionWebhookForwarder
{
    public function forward(array $payload): void
    {
        $url = config('services.evolution.webhook_forward_url');
        if (! is_string($url) || trim($url) === '') {
            return;
        }

        try {
            Http::timeout(8)->post($url, $payload);
        } catch (\Throwable $exception) {
            Log::warning('Failed to forward Evolution webhook payload.', [
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
