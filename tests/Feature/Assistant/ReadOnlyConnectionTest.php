<?php

namespace Tests\Feature\Assistant;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ReadOnlyConnectionTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = tempnam(sys_get_temp_dir(), 'pejota-readonly-test-');

        $writable = new PDO('sqlite:'.$this->databasePath);
        $writable->exec('CREATE TABLE samples (id INTEGER PRIMARY KEY, name TEXT)');
        $writable->exec("INSERT INTO samples (name) VALUES ('alpha')");
        unset($writable);

        config()->set('database.connections.readonly_under_test', [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'options' => [
                PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('readonly_under_test');
        @unlink($this->databasePath);

        parent::tearDown();
    }

    public function test_selects_work_on_the_read_only_connection(): void
    {
        $rows = DB::connection('readonly_under_test')->select('SELECT name FROM samples');

        $this->assertCount(1, $rows);
        $this->assertSame('alpha', $rows[0]->name);
    }

    public function test_updates_fail_on_the_read_only_connection(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('readonly');

        DB::connection('readonly_under_test')->update("UPDATE samples SET name = 'hacked'");
    }

    public function test_inserts_fail_on_the_read_only_connection(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('readonly');

        DB::connection('readonly_under_test')->insert("INSERT INTO samples (name) VALUES ('beta')");
    }

    public function test_the_sqlite_readonly_config_carries_the_read_only_open_flag(): void
    {
        $options = config('database.connections.sqlite_readonly.options');

        $this->assertSame(PDO::SQLITE_OPEN_READONLY, $options[PDO::SQLITE_ATTR_OPEN_FLAGS] ?? null);
        $this->assertSame(
            config('database.connections.sqlite.database'),
            config('database.connections.sqlite_readonly.database'),
        );
    }
}
