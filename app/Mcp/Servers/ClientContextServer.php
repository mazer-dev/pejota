<?php

namespace App\Mcp\Servers;

use App\Mcp\ClientContext;
use App\Mcp\Tools\ClientOverviewTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListNotesTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\ListWhatsappConversationsTool;
use App\Mcp\Tools\ListWhatsappMessagesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

/**
 * Read-only MCP server. Each connection is bound by its token to exactly one
 * client, and exposes only tools that read.
 */
class ClientContextServer extends Server
{
    protected string $name = 'PeJota';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Servidor de contexto do PeJota, o ERP do Luiz.

        Esta conexão é **somente leitura** e está presa a **um único cliente**. Nada
        aqui escreve, altera ou apaga dados, e não existe forma de alcançar outro
        cliente: o cliente vem do token da conexão, não dos argumentos.

        Use `client_overview` primeiro para saber com quem você está lidando e o que
        existe. Depois vá para `list_projects`, `list_tasks`, `get_task`, `list_notes`,
        `list_whatsapp_conversations` e `list_whatsapp_messages` conforme precisar.

        As conversas de WhatsApp costumam ser a fonte mais rica de contexto: combinados,
        prazos e pedidos do cliente aparecem lá antes de virarem tarefa.
        MARKDOWN;

    /**
     * @var array<string, array<string, bool>|\stdClass|string>
     */
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => [
            'listChanged' => false,
        ],
    ];

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ClientOverviewTool::class,
        ListProjectsTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        ListNotesTool::class,
        ListWhatsappConversationsTool::class,
        ListWhatsappMessagesTool::class,
    ];

    protected function boot(): void
    {
        if (! app()->bound(ClientContext::class)) {
            return;
        }

        $client = app(ClientContext::class)->client;

        $this->name = 'PeJota: '.$client->name;
        $this->instructions .= "\n\nCliente desta conexão: {$client->name} (id {$client->id}).";
    }
}
