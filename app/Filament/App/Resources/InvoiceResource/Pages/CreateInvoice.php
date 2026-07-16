<?php

namespace App\Filament\App\Resources\InvoiceResource\Pages;

use App\Enums\CompanySettingsEnum;
use App\Enums\InvoiceStatusEnum;
use App\Filament\App\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['number'] = CompanySettingsEnum::DOCS_INVOICE_NUMBER_LAST->getNextDocNumberFormated();

        return $data;
    }

    public function mount(): void
    {
        parent::mount();

        $cloneId = request()->query('clone');

        if ($cloneId) {
            $source = Invoice::with('items')->find($cloneId);

            if ($source) {
                $this->form->fill([
                    'status' => InvoiceStatusEnum::DRAFT,
                    'title' => $source->title,
                    'client_id' => $source->client_id,
                    'currency' => $source->currency,
                    'project_id' => $source->project_id,
                    'contract_id' => $source->contract_id,
                    'extra_info' => $source->extra_info,
                    'obs_internal' => $source->obs_internal,
                    'discount' => $source->discount,
                    'total' => $source->total,
                    'items' => $source->items
                        ->map(fn ($item) => [
                            'product_id' => $item->product_id,
                            'name' => $item->name,
                            'unit_id' => $item->unit_id,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                            'discount' => $item->discount,
                            'total' => $item->total,
                            'obs' => $item->obs,
                        ])
                        ->values()
                        ->toArray(),
                ]);
            }
        }
    }
}
