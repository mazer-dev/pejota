<?php

namespace App\Mcp\Tools;

use App\Mcp\ClientContext;
use App\Models\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Base for every tool of the client MCP server. All of them are read only and
 * take their scope from the token, never from the agent's arguments.
 */
abstract class ClientScopedTool extends Tool
{
    protected function context(): ClientContext
    {
        return app(ClientContext::class);
    }

    protected function client(): Client
    {
        return $this->context()->client;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data): Response
    {
        return Response::text((string) json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    protected function boundedLimit(mixed $limit, int $default, int $max): int
    {
        if (! is_numeric($limit)) {
            return $default;
        }

        return (int) max(1, min($max, (int) $limit));
    }

    protected function dateTime(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->format('Y-m-d H:i') : null;
    }

    protected function date(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->format('Y-m-d') : null;
    }

    protected function excerpt(?string $value, int $limit = 400): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Str::limit(trim(strip_tags($value)), $limit);
    }
}
