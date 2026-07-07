<?php

namespace App\Services\Invoicing;

use App\Enums\TimesheetDetailLevel;
use App\Enums\TimesheetGrouping;
use App\Mail\InvoiceDeliveryMailable;
use App\Models\InvoiceDelivery;
use App\Services\InvoiceService;
use App\Services\Mail\CompanyMailerFactory;
use App\Services\Timesheet\Renderers\PdfTimesheetRenderer;
use App\Services\Timesheet\TimesheetBuilder;
use App\Services\Timesheet\TimesheetLayoutRegistry;
use App\Services\Timesheet\TimesheetRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SendInvoiceService
{
    public function __construct(
        private InvoiceService $invoiceService,
        private CompanyMailerFactory $mailerFactory,
        private TimesheetBuilder $timesheetBuilder,
        private TimesheetLayoutRegistry $layoutRegistry,
        private PdfTimesheetRenderer $timesheetRenderer,
    ) {}

    public function send(InvoiceDelivery $delivery): void
    {
        $invoice = $delivery->invoice;
        $config = $invoice->company?->mailConfig;

        if ($config === null || ! $config->isComplete()) {
            throw new RuntimeException('Company mail configuration is incomplete.');
        }

        $files = $this->buildAttachments($delivery);
        $mailer = $this->mailerFactory->build($config);

        Mail::mailer($mailer)->send(new InvoiceDeliveryMailable(
            delivery: $delivery,
            files: $files,
            fromAddress: (string) $config->from_address,
            fromName: $config->from_name,
            replyToAddress: $config->reply_to,
        ));
    }

    /**
     * @return array<int, array{data: string, name: string, mime: string}>
     */
    private function buildAttachments(InvoiceDelivery $delivery): array
    {
        $invoice = $delivery->invoice;
        $files = [];

        if ($delivery->attach_invoice_pdf) {
            $files[] = [
                'data' => $this->invoiceService->generatePdf($invoice)->output(),
                'name' => 'invoice-'.$invoice->number.'.pdf',
                'mime' => 'application/pdf',
            ];
        }

        if (filled($delivery->timesheet_params)) {
            $files[] = [
                'data' => $this->buildTimesheetPdf($delivery->timesheet_params),
                'name' => 'timesheet-'.$invoice->number.'.pdf',
                'mime' => 'application/pdf',
            ];
        }

        if (filled($delivery->external_file_path) && Storage::disk('local')->exists($delivery->external_file_path)) {
            $files[] = [
                'data' => Storage::disk('local')->get($delivery->external_file_path),
                'name' => basename($delivery->external_file_path),
                'mime' => 'application/octet-stream',
            ];
        }

        return $files;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function buildTimesheetPdf(array $params): string
    {
        $request = new TimesheetRequest(
            clientId: (int) $params['clientId'],
            from: CarbonImmutable::parse($params['from']),
            to: CarbonImmutable::parse($params['to']),
            timezone: $params['timezone'],
            currency: $params['currency'],
            grouping: TimesheetGrouping::from($params['grouping']),
            detailLevel: TimesheetDetailLevel::from($params['detailLevel']),
            includeValue: (bool) $params['includeValue'],
            billableOnly: (bool) $params['billableOnly'],
            layoutKey: $params['layoutKey'],
        );

        $data = $this->timesheetBuilder->build($request);
        $layout = $this->layoutRegistry->get($request->layoutKey);

        return $this->timesheetRenderer->make($data, $layout, $request)->output();
    }
}
