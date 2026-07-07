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

        Landlord::addTenant('company_id', $delivery->company_id);

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

    private function cleanupUpload(InvoiceDelivery $delivery): void
    {
        if (filled($delivery->external_file_path) && Storage::disk('local')->exists($delivery->external_file_path)) {
            Storage::disk('local')->delete($delivery->external_file_path);
        }
    }
}
