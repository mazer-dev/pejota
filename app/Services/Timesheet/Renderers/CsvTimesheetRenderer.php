<?php

namespace App\Services\Timesheet\Renderers;

use App\Helpers\PejotaHelper;
use App\Services\Timesheet\Layouts\TimesheetLayout;
use App\Services\Timesheet\TimesheetData;
use App\Services\Timesheet\TimesheetEntry;
use App\Services\Timesheet\TimesheetGroup;
use App\Services\Timesheet\TimesheetRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvTimesheetRenderer
{
    public function render(TimesheetData $data, TimesheetLayout $layout, TimesheetRequest $request): StreamedResponse
    {
        $columns = $layout->columns($request);
        $filename = 'timesheet-'.$data->client->id.'-'.$data->from->format('Ymd').'-'.$data->to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($data, $columns): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_map(fn (array $c) => $c['label'], $columns));

            foreach ($data->groups as $group) {
                if ($group->entries->isEmpty()) {
                    fputcsv($handle, $this->groupSummaryRow($group, $columns));

                    continue;
                }

                foreach ($group->entries as $entry) {
                    fputcsv($handle, $this->entryRow($entry, $columns));
                }

                fputcsv($handle, $this->groupSummaryRow($group, $columns)); // subtotal line after a non-empty group (spec §7)
            }

            fputcsv($handle, $this->grandTotalRow($data, $columns));

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $columns
     * @return array<int, string>
     */
    private function entryRow(TimesheetEntry $entry, array $columns): array
    {
        return array_map(fn (array $c) => match ($c['key']) {
            'date' => $entry->date->format('Y-m-d'),
            'description' => (string) $entry->description,
            'taskTitle' => (string) $entry->taskTitle,
            'projectName' => (string) $entry->projectName,
            'minutes' => PejotaHelper::formatDuration($entry->minutes),
            'rate' => $entry->rate === null ? '' : number_format($entry->rate, 2),
            'value' => number_format($entry->value, 2),
            default => '',
        }, $columns);
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $columns
     * @return array<int, string>
     */
    private function groupSummaryRow(TimesheetGroup $group, array $columns): array
    {
        return array_map(fn (array $c) => match ($c['key']) {
            'date', 'description', 'projectName', 'taskTitle' => $c['key'] === $columns[0]['key'] ? $group->label : '',
            'minutes' => PejotaHelper::formatDuration($group->subtotalMinutes),
            'value' => number_format($group->subtotalValue, 2),
            default => '',
        }, $columns);
    }

    /**
     * @param  array<int, array{key: string, label: string, type: string}>  $columns
     * @return array<int, string>
     */
    private function grandTotalRow(TimesheetData $data, array $columns): array
    {
        return array_map(fn (array $c, int $i) => match (true) {
            $i === 0 => __('Total'),
            $c['key'] === 'minutes' => PejotaHelper::formatDuration($data->grandTotalMinutes),
            $c['key'] === 'value' => number_format($data->grandTotalValue, 2),
            default => '',
        }, $columns, array_keys($columns));
    }
}
