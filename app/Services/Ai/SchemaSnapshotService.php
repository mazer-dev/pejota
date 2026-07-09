<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a plain-text snapshot of the database schema (tables, columns,
 * types and foreign keys) for the AI assistant's prompt. Cached forever,
 * keyed by a hash of the migration filenames, so it invalidates itself
 * whenever a migration is added/removed.
 */
class SchemaSnapshotService
{
    /**
     * Bump when the snapshot format changes (hints, layout), since the cache
     * key otherwise only invalidates when migrations change.
     */
    private const VERSION = 2;

    /**
     * Unit hints appended to columns whose raw values the model would
     * otherwise misread: durations are stored in minutes and every
     * MoneyCast column in cents.
     */
    private const COLUMN_HINTS = [
        'work_sessions.duration' => 'duração em MINUTOS (não segundos); divida por 60 para horas',
        'work_sessions.rate' => 'valor por hora em CENTAVOS; divida por 100',
        'work_sessions.value' => 'valor em CENTAVOS; divida por 100',
        'invoices.total' => 'valor em CENTAVOS; divida por 100',
        'invoices.discount' => 'valor em CENTAVOS; divida por 100',
        'invoice_items.price' => 'valor em CENTAVOS; divida por 100',
        'invoice_items.total' => 'valor em CENTAVOS; divida por 100',
        'invoice_items.discount' => 'valor em CENTAVOS; divida por 100',
        'contracts.total' => 'valor em CENTAVOS; divida por 100',
        'projects.hourly_rate' => 'valor em CENTAVOS; divida por 100',
        'clients.default_hourly_rate' => 'valor em CENTAVOS; divida por 100',
        'tasks.hourly_rate' => 'valor em CENTAVOS; divida por 100',
        'subscriptions.price' => 'valor em CENTAVOS; divida por 100',
        'accounts.initial_balance_at' => 'valor em CENTAVOS; divida por 100',
    ];

    /**
     * Framework/infrastructure tables that add prompt noise without being
     * useful for answering questions about the user's business data.
     */
    private const EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'notifications',
        'password_reset_tokens',
        'sessions',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ];

    public function snapshot(): string
    {
        return Cache::rememberForever($this->cacheKey(), fn (): string => $this->buildSnapshot());
    }

    public function forget(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function cacheKey(): string
    {
        $migrations = collect(File::files(database_path('migrations')))
            ->map(fn ($file): string => $file->getFilename())
            ->sort()
            ->implode('|');

        return 'assistant-schema-v'.self::VERSION.'-'.md5($migrations);
    }

    private function buildSnapshot(): string
    {
        $sections = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if (in_array($name, self::EXCLUDED_TABLES, true)) {
                continue;
            }

            $columns = Schema::getColumns($name);
            $columnNames = array_column($columns, 'name');

            $header = "Tabela {$name}";
            if (in_array('company_id', $columnNames, true)) {
                $header .= ' (tenant: filtrar por company_id)';
            }

            $lines = [$header.':'];

            foreach ($columns as $column) {
                $hint = self::COLUMN_HINTS[$name.'.'.$column['name']] ?? null;

                $lines[] = '- '.$column['name'].' '.$column['type']
                    .($column['nullable'] ? ' (nullable)' : '')
                    .($hint ? ' — '.$hint : '');
            }

            foreach (Schema::getForeignKeys($name) as $foreignKey) {
                $lines[] = '- FK: '.implode(',', $foreignKey['columns'])
                    .' -> '.$foreignKey['foreign_table'].'('.implode(',', $foreignKey['foreign_columns']).')';
            }

            $sections[] = implode("\n", $lines);
        }

        return implode("\n\n", $sections);
    }
}
