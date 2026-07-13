<?php

namespace App\Console\Commands;

use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Console\Command;

class ConfigureEvolutionWebhook extends Command
{
    protected $signature = 'evolution:configure-webhook
        {--url= : URL pública do webhook}
        {--no-base64 : Não solicitar base64 de mídias no webhook}
        {--instance= : Instância Evolution alvo (default: EVOLUTION_INSTANCE)}';

    protected $description = 'Configura a Evolution API para enviar eventos de WhatsApp ao Pejota';

    public function handle(EvolutionApiClient $client): int
    {
        $url = $this->option('url') ?: $this->defaultUrl();
        if (! $url) {
            $this->error('Informe --url ou configure APP_URL e EVOLUTION_WEBHOOK_TOKEN.');

            return self::FAILURE;
        }

        $instance = $this->option('instance');

        $client->setWebhook((string) $url, ! (bool) $this->option('no-base64'), is_string($instance) && trim($instance) !== '' ? trim($instance) : null);
        $this->info('Webhook da Evolution configurado.');

        return self::SUCCESS;
    }

    private function defaultUrl(): ?string
    {
        $baseUrl = config('app.url');
        $token = config('services.evolution.webhook_token');

        if (! is_string($baseUrl) || trim($baseUrl) === '' || ! is_string($token) || trim($token) === '') {
            return null;
        }

        return rtrim($baseUrl, '/').'/webhooks/evolution?token='.urlencode($token);
    }
}
