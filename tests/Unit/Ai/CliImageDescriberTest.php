<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\AiCliRunner;
use App\Services\Ai\CliImageDescriber;
use Mockery;
use Tests\TestCase;

class CliImageDescriberTest extends TestCase
{
    public function test_it_describes_image_with_ai_cli_runner(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pejota-image-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        $runner = Mockery::mock(AiCliRunner::class);
        $runner->shouldReceive('complete')
            ->once()
            ->with(Mockery::on(fn (string $prompt): bool => str_contains($prompt, 'Descreva esta imagem recebida pelo WhatsApp')), [realpath($path)])
            ->andReturn('Imagem com uma tela de assinatura do Claude Max.');

        $describer = new CliImageDescriber($runner);

        try {
            $description = $describer->describe($path, 'image/png');
        } finally {
            @unlink($path);
        }

        $this->assertSame('Imagem com uma tela de assinatura do Claude Max.', $description);
    }
}
