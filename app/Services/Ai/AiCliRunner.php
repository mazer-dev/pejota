<?php

namespace App\Services\Ai;

use RuntimeException;
use Symfony\Component\Process\Process;

class AiCliRunner
{
    /**
     * @param  array<int, string>  $images
     */
    public function complete(string $prompt, array $images = []): string
    {
        $errors = [];

        try {
            return $this->runCodex($prompt, $images);
        } catch (RuntimeException $exception) {
            $errors[] = 'Codex: '.$exception->getMessage();
        }

        try {
            return $this->runAgy($prompt, $images);
        } catch (RuntimeException $exception) {
            $errors[] = 'AGY: '.$exception->getMessage();
        }

        throw new RuntimeException('Falha ao gerar resposta pelos CLIs de IA. '.implode(' | ', $errors));
    }

    /**
     * @param  array<int, string>  $images
     */
    private function runCodex(string $prompt, array $images): string
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'pejota-codex-');
        if ($outputFile === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para saída do Codex.');
        }

        $command = [
            ...$this->sudoPrefix(),
            (string) config('services.ai_cli.codex_bin', 'codex'),
            'exec',
            '--skip-git-repo-check',
            '--ephemeral',
            '--sandbox',
            'read-only',
            '--output-last-message',
            $outputFile,
        ];

        $model = config('services.ai_cli.codex_model');
        if (is_string($model) && trim($model) !== '') {
            $command[] = '--model';
            $command[] = trim($model);
        }

        foreach ($images as $image) {
            $command[] = '--image';
            $command[] = $image;
        }

        $command[] = '-';

        try {
            $this->runProcess($command, $prompt);

            $output = is_file($outputFile) ? trim((string) file_get_contents($outputFile)) : '';
            if ($output === '') {
                throw new RuntimeException('Codex não retornou conteúdo.');
            }

            return $output;
        } finally {
            @unlink($outputFile);
        }
    }

    /**
     * @param  array<int, string>  $images
     */
    private function runAgy(string $prompt, array $images): string
    {
        if ($images !== []) {
            $prompt .= "\n\nArquivos locais de imagem para analisar:\n".collect($images)
                ->map(fn (string $image): string => '- '.$image)
                ->implode("\n");
        }

        $command = [
            ...$this->sudoPrefix(),
            (string) config('services.ai_cli.agy_bin', 'agy'),
        ];

        if ((bool) config('services.ai_cli.agy_skip_permissions', false)) {
            $command[] = '--dangerously-skip-permissions';
        }

        $model = config('services.ai_cli.agy_model');
        if (is_string($model) && trim($model) !== '') {
            $command[] = '--model';
            $command[] = trim($model);
        }

        $command[] = '--print';
        $command[] = $prompt;

        return $this->runProcess($command);
    }

    /**
     * @param  array<int, string>  $command
     */
    private function runProcess(array $command, ?string $input = null): string
    {
        $process = new Process(
            command: $command,
            cwd: (string) config('services.ai_cli.workdir', base_path()),
            env: [
                'TERM' => 'dumb',
                'NO_COLOR' => '1',
            ],
        );

        $process->setTimeout((int) config('services.ai_cli.timeout', 300));
        if ($input !== null) {
            $process->setInput($input);
        }

        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'sem saída de erro';

            throw new RuntimeException($message);
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            throw new RuntimeException('CLI não retornou conteúdo.');
        }

        return $output;
    }

    /**
     * @return array<int, string>
     */
    private function sudoPrefix(): array
    {
        if (! (bool) config('services.ai_cli.use_sudo', false)) {
            return [];
        }

        return [
            (string) config('services.ai_cli.sudo_bin', 'sudo'),
            '-n',
            '-H',
            '-u',
            (string) config('services.ai_cli.sudo_user', 'root'),
        ];
    }
}
