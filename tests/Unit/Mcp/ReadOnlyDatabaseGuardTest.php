<?php

namespace Tests\Unit\Mcp;

use App\Mcp\Exceptions\ReadOnlyViolationException;
use App\Mcp\ReadOnlyDatabaseGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReadOnlyDatabaseGuardTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function readStatements(): array
    {
        return [
            'select' => ['select * from tasks'],
            'select with leading space' => ['   select 1'],
            'select in parenthesis' => ['(select * from tasks)'],
            'pragma' => ['pragma foreign_keys'],
            'explain' => ['explain select * from tasks'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function writeStatements(): array
    {
        return [
            'insert' => ['insert into tasks (title) values (?)'],
            'update' => ['update tasks set title = ?'],
            'delete' => ['delete from tasks where id = ?'],
            'drop' => ['drop table tasks'],
            'truncate' => ['truncate table tasks'],
            'alter' => ['alter table tasks add column x'],
            'sneaky comment prefix' => ['/* select */ delete from tasks'],
        ];
    }

    #[DataProvider('readStatements')]
    public function test_it_allows_read_statements(string $statement): void
    {
        $this->assertTrue(ReadOnlyDatabaseGuard::isReadOnlyStatement($statement));

        $guard = new ReadOnlyDatabaseGuard;
        $guard->check($statement);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('writeStatements')]
    public function test_it_blocks_write_statements(string $statement): void
    {
        $this->assertFalse(ReadOnlyDatabaseGuard::isReadOnlyStatement($statement));

        $this->expectException(ReadOnlyViolationException::class);

        (new ReadOnlyDatabaseGuard)->check($statement);
    }

    public function test_a_disabled_guard_stops_checking(): void
    {
        $guard = new ReadOnlyDatabaseGuard;
        $guard->disable();

        $guard->check('delete from tasks');

        $this->addToAssertionCount(1);
    }
}
