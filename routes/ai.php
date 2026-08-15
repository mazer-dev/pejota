<?php

use App\Http\Middleware\AuthenticateClientMcpToken;
use App\Mcp\Servers\ClientContextServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('mcp/client', ClientContextServer::class)
    ->middleware([AuthenticateClientMcpToken::class])
    ->name('mcp.client');
