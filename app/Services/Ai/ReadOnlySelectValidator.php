<?php

namespace App\Services\Ai;

use InvalidArgumentException;

/**
 * Validates that a SQL string produced by the AI assistant is a single,
 * read-only SELECT statement (optionally prefixed by a CTE: WITH ... SELECT).
 *
 * This is defense-in-depth only: the assistant's queries always run on the
 * sqlite_readonly connection (opened with SQLITE_OPEN_READONLY), so even a
 * statement that slipped past this validator could not write to the database.
 */
class ReadOnlySelectValidator
{
    /**
     * Keywords rejected anywhere in the statement (as whole words).
     * REPLACE is handled separately because replace() is also a legitimate
     * SQLite scalar function inside SELECTs; only "REPLACE INTO" is blocked.
     */
    private const FORBIDDEN_KEYWORDS = [
        'insert',
        'update',
        'delete',
        'drop',
        'alter',
        'create',
        'truncate',
        'pragma',
        'attach',
        'detach',
        'vacuum',
        'reindex',
        'grant',
        'revoke',
    ];

    /**
     * Returns the normalized statement (trailing semicolon stripped) or
     * throws InvalidArgumentException with a message the AI loop can relay.
     */
    public function validate(string $sql): string
    {
        $normalized = trim($sql);
        $normalized = rtrim($normalized, "; \t\n\r");
        $normalized = trim($normalized);

        if ($normalized === '') {
            throw new InvalidArgumentException('Consulta vazia.');
        }

        if (str_contains($normalized, ';')) {
            throw new InvalidArgumentException('Apenas um único statement é permitido (sem ";").');
        }

        if (! preg_match('/^(select|with)\b/i', $normalized)) {
            throw new InvalidArgumentException('Apenas consultas SELECT são permitidas.');
        }

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/i', $normalized)) {
                throw new InvalidArgumentException("Palavra-chave não permitida na consulta: {$keyword}.");
            }
        }

        if (preg_match('/\breplace\s+into\b/i', $normalized)) {
            throw new InvalidArgumentException('Palavra-chave não permitida na consulta: replace into.');
        }

        return $normalized;
    }
}
