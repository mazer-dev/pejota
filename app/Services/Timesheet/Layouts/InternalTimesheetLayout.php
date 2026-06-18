<?php

namespace App\Services\Timesheet\Layouts;

use App\Enums\TimesheetDetailLevel;
use App\Services\Timesheet\TimesheetRequest;

class InternalTimesheetLayout implements TimesheetLayout
{
    public function key(): string
    {
        return 'internal';
    }

    public function label(): string
    {
        return __('Internal (raw)');
    }

    public function columns(TimesheetRequest $request): array
    {
        $columns = [
            ['key' => 'date', 'label' => __('Date'), 'type' => 'date'],
            ['key' => 'projectName', 'label' => __('Project'), 'type' => 'text'],
            ['key' => 'taskTitle', 'label' => __('Task'), 'type' => 'text'],
            ['key' => 'description', 'label' => __('Description'), 'type' => 'text'],
            ['key' => 'minutes', 'label' => __('Time'), 'type' => 'duration'],
        ];

        if ($request->includeValue) {
            if ($request->detailLevel === TimesheetDetailLevel::Detailed) {
                $columns[] = ['key' => 'rate', 'label' => __('Rate'), 'type' => 'money'];
            }
            $columns[] = ['key' => 'value', 'label' => __('Value'), 'type' => 'money'];
        }

        return $columns;
    }

    public function headerView(): ?string
    {
        return null;
    }
}
