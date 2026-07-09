<?php

namespace App\Services\Ai;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Helpers\PejotaHelper;
use App\Models\AssistantConversation;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The assistant's only write capability: creating an invoice behind a
 * server-side passphrase. The model only proposes a draft; validation,
 * passphrase generation, comparison and the actual creation are all
 * deterministic PHP — the model can never create anything by itself, and
 * prompt-injected content cannot fake a confirmation.
 */
class AssistantInvoiceService
{
    private const EXPIRES_MINUTES = 15;

    /**
     * Accent-free so the passphrase is easy to type exactly (comparison is
     * case-sensitive by design).
     */
    private const PASSPHRASE_WORDS = [
        'Girassol', 'Horizonte', 'Cascata', 'Aurora', 'Miragem', 'Farol',
        'Trilha', 'Rochedo', 'Cometa', 'Nebulosa', 'Moinho', 'Pomar',
        'Recife', 'Vendaval', 'Alvorada', 'Penhasco', 'Riacho', 'Lampejo',
        'Veleiro', 'Estaleiro',
    ];

    /**
     * Intercepts the user's message before any AI runs. Returns a final
     * deterministic answer when the message resolves the pending draft
     * (invoice created or creation failed), or null to let the normal
     * agentic loop handle the message.
     */
    public function handleConfirmation(AssistantConversation $conversation, ?string $userMessage): ?string
    {
        $pending = $this->pending($conversation);

        if ($pending === null || $userMessage === null) {
            return null;
        }

        if (trim($userMessage) !== (string) $pending['passphrase']) {
            return null;
        }

        try {
            $invoice = $this->createInvoice($pending['draft']);
        } catch (Throwable $exception) {
            report($exception);

            return 'A palavra-passe confere, mas a criação da fatura falhou: '.$exception->getMessage()
                .' O rascunho continua pendente; você pode tentar novamente ou pedir ajustes.';
        }

        $this->clearPending($conversation);

        return $this->successMessage($invoice);
    }

    /**
     * Returns the non-expired pending create_invoice action, clearing it
     * when expired.
     *
     * @return array{draft: array<string, mixed>, passphrase: string, expires_at: string}|null
     */
    public function pending(AssistantConversation $conversation): ?array
    {
        $action = $conversation->pending_action;

        if (! is_array($action) || ($action['type'] ?? null) !== 'create_invoice') {
            return null;
        }

        if (Carbon::parse($action['expires_at'])->isPast()) {
            $this->clearPending($conversation);

            return null;
        }

        return $action;
    }

    /**
     * Validates and normalizes a draft proposed by the model.
     *
     * @param  array<string, mixed>  $draft
     * @return array{0: ?array<string, mixed>, 1: array<int, string>}
     */
    public function validateDraft(array $draft, int $companyId): array
    {
        $errors = [];

        $client = Client::allTenants()
            ->where('company_id', $companyId)
            ->find((int) ($draft['client_id'] ?? 0));
        if (! $client) {
            $errors[] = 'client_id inexistente: consulte a tabela clients e use um id válido.';
        }

        $projectId = null;
        if (! empty($draft['project_id'])) {
            $project = Project::allTenants()
                ->where('company_id', $companyId)
                ->find((int) $draft['project_id']);
            if (! $project) {
                $errors[] = 'project_id inexistente.';
            } elseif ($client && $project->client_id && (int) $project->client_id !== (int) $client->id) {
                $errors[] = 'project_id não pertence ao client_id informado.';
            } else {
                $projectId = $project?->id;
            }
        }

        $title = trim((string) ($draft['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'title é obrigatório (um título curto para a fatura).';
        }

        $dueDate = $this->parseDueDate($draft['due_date'] ?? null);
        if ($dueDate === null) {
            $errors[] = 'due_date é obrigatória no formato YYYY-MM-DD. NUNCA invente: se o Luiz ainda não disse o vencimento, pergunte a ele antes de propor a fatura.';
        }

        [$items, $itemErrors] = $this->validateItems($draft['items'] ?? null, $companyId);
        array_push($errors, ...$itemErrors);

        $discountCents = (int) ($draft['discount_cents'] ?? 0);
        if ($discountCents < 0) {
            $errors[] = 'discount_cents não pode ser negativo.';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        $totalCents = array_sum(array_column($items, 'total_cents')) - $discountCents;
        if ($totalCents < 0) {
            return [null, ['O desconto é maior que a soma dos itens.']];
        }

        return [[
            'company_id' => $companyId,
            'client_id' => $client->id,
            'client_name' => $client->name,
            'project_id' => $projectId,
            'title' => $title,
            'due_date' => $dueDate->toDateString(),
            'currency' => $client->currency ?? PejotaHelper::getUserCurrency(),
            'items' => $items,
            'discount_cents' => $discountCents,
            'total_cents' => $totalCents,
            'extra_info' => filled($draft['extra_info'] ?? null) ? trim((string) $draft['extra_info']) : null,
            'obs_internal' => filled($draft['obs_internal'] ?? null) ? trim((string) $draft['obs_internal']) : null,
        ], []];
    }

    /**
     * Stores the pending draft with a server-generated passphrase and
     * returns the message shown to the user (summary + passphrase).
     *
     * @param  array<string, mixed>  $draft
     */
    public function beginConfirmation(AssistantConversation $conversation, array $draft): string
    {
        $passphrase = self::PASSPHRASE_WORDS[random_int(0, count(self::PASSPHRASE_WORDS) - 1)];

        $conversation->forceFill([
            'pending_action' => [
                'type' => 'create_invoice',
                'draft' => $draft,
                'passphrase' => $passphrase,
                'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES)->toISOString(),
            ],
        ])->save();

        return $this->summaryMessage($draft, $passphrase);
    }

    public function clearPending(AssistantConversation $conversation): void
    {
        $conversation->forceFill(['pending_action' => null])->save();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function summaryMessage(array $draft, string $passphrase): string
    {
        $lines = [
            'Confira os dados da fatura antes de confirmar:',
            '',
            '• Cliente: '.$draft['client_name'],
        ];

        if ($draft['project_id']) {
            $projectName = Project::allTenants()->find($draft['project_id'])?->name;
            if ($projectName) {
                $lines[] = '• Projeto: '.$projectName;
            }
        }

        $lines[] = '• Título: '.$draft['title'];
        $lines[] = '• Vencimento: '.Carbon::parse($draft['due_date'])->format('d/m/Y');
        $lines[] = '• Itens:';

        foreach ($draft['items'] as $item) {
            $lines[] = '   - '.$item['name'].' — '.$item['quantity'].' × '
                .$this->money($item['price_cents'], $draft['currency'])
                .' = '.$this->money($item['total_cents'], $draft['currency']);
        }

        if ($draft['discount_cents'] > 0) {
            $lines[] = '• Desconto: '.$this->money($draft['discount_cents'], $draft['currency']);
        }

        $lines[] = '• Total: '.$this->money($draft['total_cents'], $draft['currency']);
        $lines[] = '• Status ao criar: Enviada';
        $lines[] = '';
        $lines[] = 'Para confirmar e criar a fatura, digite exatamente esta palavra (diferencia maiúsculas de minúsculas):';
        $lines[] = $passphrase;
        $lines[] = '';
        $lines[] = 'Qualquer outra mensagem NÃO cria a fatura — você pode pedir ajustes normalmente. Esta confirmação vale por '.self::EXPIRES_MINUTES.' minutos.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    private function createInvoice(array $draft): Invoice
    {
        return DB::transaction(function () use ($draft): Invoice {
            $invoice = Invoice::create([
                'company_id' => $draft['company_id'],
                'number' => CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumberFormated(),
                'title' => $draft['title'],
                'client_id' => $draft['client_id'],
                'project_id' => $draft['project_id'],
                'currency' => $draft['currency'],
                'status' => InvoiceStatusEnum::SENT,
                'due_date' => $draft['due_date'],
                'total' => $draft['total_cents'] / 100,
                'discount' => $draft['discount_cents'] > 0 ? $draft['discount_cents'] / 100 : null,
                'extra_info' => $draft['extra_info'],
                'obs_internal' => $draft['obs_internal'],
            ]);

            foreach ($draft['items'] as $item) {
                $invoice->items()->create([
                    'product_id' => $item['product_id'],
                    'unit_id' => $item['unit_id'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price_cents'] / 100,
                    'total' => $item['total_cents'] / 100,
                    'obs' => $item['obs'],
                ]);
            }

            return $invoice;
        });
    }

    private function successMessage(Invoice $invoice): string
    {
        return 'Fatura '.$invoice->number.' criada com status Enviada: '.$invoice->title
            .' — total '.$this->money((int) round($invoice->total * 100), $invoice->currency)
            .', vencimento '.$invoice->due_date?->format('d/m/Y')
            .'. Você pode revisá-la em Finanças > Faturas.';
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function validateItems(mixed $items, int $companyId): array
    {
        if (! is_array($items) || $items === [] || ! array_is_list($items)) {
            return [[], ['items é obrigatório: uma lista com ao menos um item {name, quantity, price_cents}.']];
        }

        $defaultProductId = (int) (auth()->user()?->company->settings()->get(CompanySettingsEnum::INVOICE_SESSION_PRODUCT->value) ?? 0);
        $defaultUnitId = (int) (auth()->user()?->company->settings()->get(CompanySettingsEnum::INVOICE_SESSION_UNIT->value) ?? 0);

        $errors = [];
        $normalized = [];

        foreach ($items as $index => $item) {
            $position = $index + 1;

            if (! is_array($item)) {
                $errors[] = "Item {$position}: formato inválido.";

                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                $errors[] = "Item {$position}: name (descrição na fatura) é obrigatório.";
            }

            $quantity = $item['quantity'] ?? null;
            if (! is_numeric($quantity) || (float) $quantity <= 0) {
                $errors[] = "Item {$position}: quantity deve ser um número maior que zero.";
            }

            $priceCents = $item['price_cents'] ?? null;
            if (! is_numeric($priceCents) || (int) $priceCents < 0 || (float) $priceCents !== (float) (int) $priceCents) {
                $errors[] = "Item {$position}: price_cents deve ser um inteiro em centavos (ex.: R$ 90,00 = 9000).";
            }

            $productId = (int) ($item['product_id'] ?? 0) ?: $defaultProductId;
            $unitId = (int) ($item['unit_id'] ?? 0) ?: $defaultUnitId;

            if (! $productId || ! Product::allTenants()->where('company_id', $companyId)->whereKey($productId)->exists()) {
                $errors[] = "Item {$position}: product_id é obrigatório e deve existir (consulte a tabela products).";
            }

            if (! $unitId || ! Unit::allTenants()->where('company_id', $companyId)->whereKey($unitId)->exists()) {
                $errors[] = "Item {$position}: unit_id é obrigatório e deve existir (consulte a tabela units).";
            }

            if ($errors !== []) {
                continue;
            }

            $totalCents = (int) round((float) $quantity * (int) $priceCents);

            $normalized[] = [
                'name' => $name,
                'quantity' => (float) $quantity,
                'price_cents' => (int) $priceCents,
                'total_cents' => $totalCents,
                'product_id' => $productId,
                'unit_id' => $unitId,
                'obs' => filled($item['obs'] ?? null) ? trim((string) $item['obs']) : null,
            ];
        }

        return [$errors === [] ? $normalized : [], $errors];
    }

    private function parseDueDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function money(int $cents, ?string $currency): string
    {
        return trim(($currency ? $currency.' ' : '').number_format($cents / 100, 2, ',', '.'));
    }
}
