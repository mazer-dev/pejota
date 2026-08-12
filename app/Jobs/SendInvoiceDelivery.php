<?php

namespace App\Jobs;

use App\Enums\DeliveryStatusEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\InvoiceDelivery;
use App\Services\Invoicing\SendInvoiceService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use NunoMazer\Samehouse\Facades\Landlord;
use Throwable;

class SendInvoiceDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $deliveryId) {}

    public function handle(SendInvoiceService $service): void
    {
        $delivery = InvoiceDelivery::findOrFail($this->deliveryId);

        if ($delivery->status === DeliveryStatusEnum::Sent) {
            return;
        }

        $previousTenantId = Landlord::hasTenant('company_id')
            ? Landlord::getTenantId('company_id')
            : null;

        Landlord::addTenant('company_id', $delivery->company_id);

        try {
            $service->send($delivery);

            $delivery->update([
                'status' => DeliveryStatusEnum::Sent,
                'sent_at' => now(),
            ]);

            $invoice = $delivery->invoice;
            if ($invoice->status === InvoiceStatusEnum::DRAFT) {
                $invoice->update(['status' => InvoiceStatusEnum::SENT]);
            }

            $this->cleanupUpload($delivery);

            if ($delivery->creator) {
                Notification::make()
                    ->title(__('Invoice sent'))
                    ->body(__('Invoice :number was sent.', ['number' => $invoice->number]))
                    ->success()
                    ->sendToDatabase($delivery->creator);
            }
        } finally {
            $this->restoreTenant($previousTenantId);
        }
    }

    public function failed(Throwable $exception): void
    {
        $delivery = InvoiceDelivery::find($this->deliveryId);
        if ($delivery === null) {
            return;
        }

        $delivery->update([
            'status' => DeliveryStatusEnum::Failed,
            'error' => $exception->getMessage(),
        ]);

        $this->cleanupUpload($delivery);

        if ($delivery->creator) {
            Notification::make()
                ->title(__('Invoice send failed'))
                ->body($exception->getMessage())
                ->danger()
                ->sendToDatabase($delivery->creator);
        }
    }

    /**
     * `Landlord` é singleton e sobrevive ao job num worker de fila persistente,
     * então o tenant que este job define tem de ser desfeito — senão o job
     * seguinte, de outra empresa, o herda.
     *
     * RESTAURA o valor anterior em vez de simplesmente remover, e a diferença é
     * load-bearing: `applyTenantScopes()` grava na CLASSE do model um global
     * scope cujo closure chama `getTenantId()` a cada query, e esse método
     * LANÇA quando o tenant não está mais lá. Remover às cegas quebraria todo
     * chamador que já tivesse models escopados — que é o caso do painel, onde o
     * job roda dentro da própria request em `QUEUE_CONNECTION=sync`.
     */
    private function restoreTenant(mixed $previousTenantId): void
    {
        if ($previousTenantId === null) {
            Landlord::removeTenant('company_id');

            return;
        }

        Landlord::addTenant('company_id', $previousTenantId);
    }

    private function cleanupUpload(InvoiceDelivery $delivery): void
    {
        if (filled($delivery->external_file_path) && Storage::disk('local')->exists($delivery->external_file_path)) {
            Storage::disk('local')->delete($delivery->external_file_path);
        }
    }
}
