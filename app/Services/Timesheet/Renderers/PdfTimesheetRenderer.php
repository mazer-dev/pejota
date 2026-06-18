<?php

namespace App\Services\Timesheet\Renderers;

use App\Services\Timesheet\Layouts\TimesheetLayout;
use App\Services\Timesheet\TimesheetData;
use App\Services\Timesheet\TimesheetRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfTimesheetRenderer
{
    public function make(TimesheetData $data, TimesheetLayout $layout, TimesheetRequest $request): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadHtml(
            view('timesheet.pdf', compact('data', 'layout', 'request'))->render()
        );
    }

    public function download(TimesheetData $data, TimesheetLayout $layout, TimesheetRequest $request): StreamedResponse
    {
        $filename = 'timesheet-'.$data->client->id.'-'.$data->from->format('Ymd').'-'.$data->to->format('Ymd').'.pdf';

        return response()->streamDownload(
            fn () => print ($this->make($data, $layout, $request)->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
