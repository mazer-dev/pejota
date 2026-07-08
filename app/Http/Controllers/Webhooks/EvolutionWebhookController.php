<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Evolution\EvolutionWebhookForwarder;
use App\Services\Evolution\EvolutionWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EvolutionWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        EvolutionWebhookHandler $handler,
        EvolutionWebhookForwarder $forwarder,
    ): JsonResponse {
        $this->authorizeWebhook($request);

        $payload = $request->all();
        $handled = $handler->handle($payload);
        $forwarder->forward($payload);

        return response()->json([
            'ok' => true,
            'handled' => $handled,
        ]);
    }

    private function authorizeWebhook(Request $request): void
    {
        $token = config('services.evolution.webhook_token');
        if (is_string($token) && $token !== '' && ! hash_equals($token, (string) $request->query('token'))) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if (! (bool) config('services.evolution.webhook_verify_api_key', true)) {
            return;
        }

        $apiKey = config('services.evolution.api_key');
        $payloadKey = $request->input('apikey');

        if (is_string($apiKey) && $apiKey !== '' && is_string($payloadKey) && $payloadKey !== '' && ! hash_equals($apiKey, $payloadKey)) {
            abort(Response::HTTP_UNAUTHORIZED);
        }
    }
}
