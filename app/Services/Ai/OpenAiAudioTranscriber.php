<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OpenAiAudioTranscriber
{
    private const SUPPORTED_EXTENSIONS = [
        'flac',
        'm4a',
        'mp3',
        'mp4',
        'mpeg',
        'mpga',
        'oga',
        'ogg',
        'wav',
        'webm',
    ];

    private const NEEDS_CONVERSION = [
        'opus',
    ];

    public function transcribe(string $filePath, ?string $prompt = null, ?string $language = null): string
    {
        $sourcePath = $this->normalizePath($filePath);
        $preparedPath = $this->prepareAudioFile($sourcePath);
        $handle = fopen($preparedPath, 'rb');

        if ($handle === false) {
            $this->deletePreparedFile($sourcePath, $preparedPath);

            throw new RuntimeException("Não foi possível abrir o áudio para transcrição: {$preparedPath}");
        }

        try {
            $response = Http::timeout((int) config('services.openai.timeout', 120))
                ->withToken($this->apiKey())
                ->attach('file', $handle, basename($preparedPath))
                ->post($this->endpoint('/audio/transcriptions'), array_filter([
                    'model' => config('services.openai.audio_transcription_model', 'gpt-4o-transcribe'),
                    'response_format' => 'json',
                    'prompt' => $prompt ?: config('services.openai.audio_transcription_prompt'),
                    'language' => $language ?: config('services.openai.audio_transcription_language'),
                ], fn ($value): bool => filled($value)));

            $response->throw();

            $text = $response->json('text');
            if (! is_string($text) || trim($text) === '') {
                throw new RuntimeException('A API da OpenAI não retornou texto de transcrição.');
            }

            return trim($text);
        } catch (RequestException $exception) {
            $message = $exception->response?->json('error.message')
                ?: $exception->response?->body()
                ?: $exception->getMessage();

            throw new RuntimeException("Falha na transcrição pela OpenAI: {$message}", previous: $exception);
        } finally {
            fclose($handle);
            $this->deletePreparedFile($sourcePath, $preparedPath);
        }
    }

    private function normalizePath(string $filePath): string
    {
        $realPath = realpath($filePath);
        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException("Arquivo de áudio não encontrado: {$filePath}");
        }

        $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true) && ! in_array($extension, self::NEEDS_CONVERSION, true)) {
            throw new RuntimeException("Extensão de áudio não suportada: .{$extension}");
        }

        $maxBytes = (int) config('services.openai.audio_max_mb', 25) * 1024 * 1024;
        if (filesize($realPath) > $maxBytes) {
            throw new RuntimeException('Arquivo de áudio excede o limite configurado para transcrição.');
        }

        return $realPath;
    }

    private function prepareAudioFile(string $sourcePath): string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (! in_array($extension, self::NEEDS_CONVERSION, true)) {
            return $sourcePath;
        }

        return $this->convertToWebm($sourcePath);
    }

    private function convertToWebm(string $sourcePath): string
    {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg === '') {
            throw new RuntimeException('Áudios .opus exigem ffmpeg instalado para conversão antes da transcrição.');
        }

        $targetPath = sys_get_temp_dir().'/pejota-openai-audio-'.Str::uuid().'.webm';
        $process = new Process([
            $ffmpeg,
            '-y',
            '-i',
            $sourcePath,
            '-vn',
            '-c:a',
            'libopus',
            '-b:a',
            '32k',
            $targetPath,
        ]);

        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($targetPath)) {
            @unlink($targetPath);

            throw new ProcessFailedException($process);
        }

        return $targetPath;
    }

    private function apiKey(): string
    {
        $apiKey = config('services.openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        return $apiKey;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').$path;
    }

    private function deletePreparedFile(string $sourcePath, string $preparedPath): void
    {
        if ($preparedPath !== $sourcePath && is_file($preparedPath)) {
            @unlink($preparedPath);
        }
    }
}
