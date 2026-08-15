<?php

namespace App\Mcp;

use App\Models\ClientMcpToken;
use Illuminate\Support\Str;

/**
 * Builds the ready to paste connection settings shown in the PeJota screen.
 */
class ClientMcpConnection
{
    public const TOKEN_PLACEHOLDER = '<SEU_TOKEN>';

    public static function endpoint(): string
    {
        return url('/mcp/client');
    }

    public static function serverName(ClientMcpToken $token): string
    {
        $slug = Str::slug((string) $token->client?->name);

        return 'pejota-'.($slug !== '' ? $slug : 'cliente-'.$token->client_id);
    }

    public static function claudeCommand(ClientMcpToken $token, ?string $plainToken = null): string
    {
        return sprintf(
            'claude mcp add --transport http %s %s --header "Authorization: Bearer %s"',
            self::serverName($token),
            self::endpoint(),
            $plainToken ?? self::TOKEN_PLACEHOLDER,
        );
    }

    public static function jsonConfig(ClientMcpToken $token, ?string $plainToken = null): string
    {
        return (string) json_encode([
            'mcpServers' => [
                self::serverName($token) => [
                    'type' => 'http',
                    'url' => self::endpoint(),
                    'headers' => [
                        'Authorization' => 'Bearer '.($plainToken ?? self::TOKEN_PLACEHOLDER),
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function capabilities(ClientMcpToken $token): string
    {
        return implode("\n", [
            '- '.__('Reads only. It cannot create, change or delete anything in PeJota.'),
            '- '.__('Sees only :client. No other client is reachable through this connection.', [
                'client' => (string) $token->client?->name,
            ]),
            '- '.__('Tools: client overview, projects, tasks, task detail, notes, WhatsApp conversations and messages.'),
            '- '.__('Invoices and amounts are not exposed.'),
            '- '.__('You can revoke this access at any moment on this screen.'),
        ]);
    }
}
