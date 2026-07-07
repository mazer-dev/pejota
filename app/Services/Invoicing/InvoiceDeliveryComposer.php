<?php

namespace App\Services\Invoicing;

use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Helpers\PejotaHelper;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Carbon\CarbonImmutable;

class InvoiceDeliveryComposer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function compose(Invoice $invoice, array $data, int $userId): InvoiceDelivery
    {
        $timesheetParams = null;

        if ($data['attach_timesheet'] ?? false) {
            $tz = PejotaHelper::getUserTimeZone() ?? 'UTC';
            $timesheetParams = [
                'clientId' => $invoice->client_id,
                'from' => CarbonImmutable::parse($data['timesheet_from'], $tz)->startOfDay()->setTimezone('UTC')->toIso8601String(),
                'to' => CarbonImmutable::parse($data['timesheet_to'], $tz)->endOfDay()->setTimezone('UTC')->toIso8601String(),
                'timezone' => $tz,
                'currency' => $invoice->currency ?? PejotaHelper::getUserCurrency(),
                'grouping' => TimesheetGrouping::None->value,
                'detailLevel' => TimesheetDetailLevel::Detailed->value,
                'includeValue' => true,
                'billableOnly' => false,
                'layoutKey' => $data['timesheet_layout'] ?? 'client',
            ];
        }

        $attachmentsMeta = [];
        if ($data['attach_invoice_pdf'] ?? true) {
            $attachmentsMeta[] = 'invoice-'.$invoice->number.'.pdf';
        }
        if ($timesheetParams !== null) {
            $attachmentsMeta[] = 'timesheet-'.$invoice->number.'.pdf';
        }
        if (filled($data['external_file_path'] ?? null)) {
            $attachmentsMeta[] = basename($data['external_file_path']);
        }

        return $invoice->deliveries()->create([
            'created_by' => $userId,
            'channel' => 'email',
            'status' => 'queued',
            'to' => array_values($data['to']),
            'cc' => filled($data['cc'] ?? null) ? array_values($data['cc']) : null,
            'subject' => $data['subject'],
            'body' => $data['body'] ?? null,
            'signature' => $data['signature'] ?? null,
            'attach_invoice_pdf' => (bool) ($data['attach_invoice_pdf'] ?? true),
            'timesheet_params' => $timesheetParams,
            'external_file_path' => $data['external_file_path'] ?? null,
            'attachments_meta' => $attachmentsMeta,
        ]);
    }
}
