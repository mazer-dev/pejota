<?php

namespace App\Console\Commands;

use App\Services\Ai\OpenAiAudioTranscriber;
use Illuminate\Console\Command;
use Throwable;

class TranscribeAudio extends Command
{
    protected $signature = 'ai:transcribe-audio
        {path : Caminho do arquivo de áudio}
        {--prompt= : Contexto opcional para melhorar a transcrição}
        {--language= : Código de idioma opcional, ex: pt, en, es}';

    protected $description = 'Transcreve um arquivo de áudio usando a API de transcrição da OpenAI';

    public function handle(OpenAiAudioTranscriber $transcriber): int
    {
        try {
            $text = $transcriber->transcribe(
                (string) $this->argument('path'),
                $this->option('prompt') ? (string) $this->option('prompt') : null,
                $this->option('language') ? (string) $this->option('language') : null,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($text);

        return self::SUCCESS;
    }
}
