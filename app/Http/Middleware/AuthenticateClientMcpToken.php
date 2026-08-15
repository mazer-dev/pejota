<?php

namespace App\Http\Middleware;

use App\Mcp\ClientContext;
use App\Mcp\ReadOnlyDatabaseGuard;
use App\Models\Client;
use App\Models\ClientMcpToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use NunoMazer\Samehouse\Facades\Landlord;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates an MCP connection from its token and binds it to the single
 * client that token was issued for. The token is the identity: there is no
 * logged in user and no way to ask for a different client.
 */
class AuthenticateClientMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $this->resolvePlainToken($request);

        if ($plainToken === null) {
            return $this->unauthorized('Token de acesso MCP ausente. Envie o header "Authorization: Bearer <token>".');
        }

        $token = ClientMcpToken::findActiveByPlainToken($plainToken);

        if (! $token instanceof ClientMcpToken) {
            return $this->unauthorized('Token de acesso MCP inválido ou revogado.');
        }

        $client = Client::query()
            ->withoutGlobalScopes()
            ->whereKey($token->client_id)
            ->where('company_id', $token->company_id)
            ->first();

        if (! $client instanceof Client) {
            return $this->unauthorized('O cliente vinculado a este token não existe mais.');
        }

        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        Landlord::addTenant('company_id', (int) $token->company_id);
        Landlord::applyTenantScopesToDeferredModels();

        app()->instance(ClientContext::class, new ClientContext($client, $token));

        $previousConnection = $this->switchToReadOnlyConnection();
        $guard = ReadOnlyDatabaseGuard::armOn(DB::connection());

        try {
            return $next($request);
        } finally {
            $guard->disable();
            DB::setDefaultConnection($previousConnection);
        }
    }

    /**
     * Everything the tools read goes through the read-only connection, the same
     * one the AI assistant uses. Returns the connection to restore afterwards.
     */
    protected function switchToReadOnlyConnection(): string
    {
        $previousConnection = (string) config('database.default');
        $readOnlyConnection = (string) config('services.mcp.db_connection', 'sqlite_readonly');

        if ($readOnlyConnection !== '' && config()->has('database.connections.'.$readOnlyConnection)) {
            DB::setDefaultConnection($readOnlyConnection);
        }

        return $previousConnection;
    }

    protected function resolvePlainToken(Request $request): ?string
    {
        $candidates = [
            $request->bearerToken(),
            $request->header('X-Pejota-Mcp-Token'),
            $request->query('token'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    protected function unauthorized(string $message): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => $message,
            ],
            'id' => null,
        ], 401);
    }
}
