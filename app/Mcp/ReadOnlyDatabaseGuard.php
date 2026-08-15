<?php

namespace App\Mcp;

use App\Mcp\Exceptions\ReadOnlyViolationException;
use Illuminate\Database\Connection;

/**
 * Last line of defence for the client MCP server: while enabled, any statement
 * that is not a read blows up before it reaches the database. The tools only
 * ever read, so this should never fire; it exists so that a future write can
 * never leak in through a new tool, a model event or a package side effect.
 */
class ReadOnlyDatabaseGuard
{
    protected bool $enabled = true;

    public static function armOn(Connection $connection): self
    {
        $guard = new self;

        $connection->beforeExecuting(function (string $query) use ($guard): void {
            $guard->check($query);
        });

        return $guard;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function check(string $query): void
    {
        if (! $this->enabled) {
            return;
        }

        if (self::isReadOnlyStatement($query)) {
            return;
        }

        throw new ReadOnlyViolationException(
            'O MCP do PeJota é somente leitura e bloqueou uma escrita no banco: '.mb_substr(trim($query), 0, 200)
        );
    }

    public static function isReadOnlyStatement(string $query): bool
    {
        $normalized = ltrim($query, " \t\r\n(");

        return (bool) preg_match('/^(select|pragma|explain|show|describe)\b/i', $normalized);
    }
}
