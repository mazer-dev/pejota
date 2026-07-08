<?php

namespace Tests\Unit\Ai;

use App\Services\Ai\ReadOnlySelectValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReadOnlySelectValidatorTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function acceptedQueries(): array
    {
        return [
            'plain select' => ['SELECT * FROM tasks WHERE company_id = 1'],
            'select with trailing semicolon' => ['SELECT id FROM clients;'],
            'lowercase select' => ['select count(*) from invoices where company_id = 1'],
            'cte select' => ['WITH abertas AS (SELECT * FROM tasks) SELECT * FROM abertas LIMIT 10'],
            'select with replace function' => ["SELECT replace(name, 'a', 'b') FROM clients"],
            'select over created_at and deleted_at columns' => ['SELECT created_at, updated_at FROM tasks'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedQueries(): array
    {
        return [
            'update' => ["UPDATE tasks SET title = 'x'"],
            'delete' => ['DELETE FROM tasks'],
            'insert' => ["INSERT INTO tasks (title) VALUES ('x')"],
            'drop' => ['DROP TABLE tasks'],
            'create' => ['CREATE TABLE evil (id INTEGER)'],
            'pragma' => ['PRAGMA query_only = 0'],
            'attach' => ["ATTACH DATABASE '/tmp/other.db' AS other"],
            'multiple statements' => ["SELECT 1; UPDATE tasks SET title = 'x'"],
            'extra semicolon between statements' => ['SELECT 1; SELECT 2'],
            'cte hiding an insert' => ["WITH x AS (SELECT 1) INSERT INTO tasks (title) VALUES ('x')"],
            'replace into' => ["REPLACE INTO tasks (id, title) VALUES (1, 'x')"],
            'empty string' => ['   '],
            'not a select' => ['EXPLAIN SELECT 1'],
        ];
    }

    #[DataProvider('acceptedQueries')]
    public function test_it_accepts_read_only_selects(string $sql): void
    {
        $normalized = (new ReadOnlySelectValidator)->validate($sql);

        $this->assertStringNotContainsString(';', $normalized);
    }

    #[DataProvider('rejectedQueries')]
    public function test_it_rejects_non_select_or_multi_statement_sql(string $sql): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ReadOnlySelectValidator)->validate($sql);
    }
}
