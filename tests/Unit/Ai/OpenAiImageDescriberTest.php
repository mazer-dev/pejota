<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\OpenAiImageDescriber;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiImageDescriberTest extends TestCase
{
    public function test_it_describes_image_with_openai_vision_input(): void
    {
        config([
            'services.openai.api_key' => 'openai-secret',
            'services.openai.base_url' => 'http://openai.test',
            'services.openai.image_model' => 'gpt-4o-mini',
        ]);

        Http::fake([
            'http://openai.test/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Imagem com uma tela de assinatura do Claude Max.',
                        ],
                    ],
                ],
            ]),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'pejota-image-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        try {
            $description = app(OpenAiImageDescriber::class)->describe($path, 'image/png');
        } finally {
            @unlink($path);
        }

        $this->assertSame('Imagem com uma tela de assinatura do Claude Max.', $description);
        Http::assertSent(function ($request): bool {
            $content = $request['messages'][1]['content'];

            return $request->url() === 'http://openai.test/chat/completions'
                && $request['model'] === 'gpt-4o-mini'
                && $content[1]['type'] === 'image_url'
                && str_starts_with($content[1]['image_url']['url'], 'data:image/png;base64,');
        });
    }
}
