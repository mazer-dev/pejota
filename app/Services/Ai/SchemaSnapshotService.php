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

        return 'assistant-schema-'.md5($migrations);
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
                $lines[] = '- '.$column['name'].' '.$column['type'].($column['nullable'] ? ' (nullable)' : '');
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
