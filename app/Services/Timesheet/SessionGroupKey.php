<?php

namespace App\Services\Timesheet;

use App\Enums\TimesheetGrouping;
use App\Models\WorkSession;

class SessionGroupKey
{
    /**
     * The label/key a work session groups under for a given grouping dimension.
     * Period dimensions bucket by the session start converted to $timezone (start is UTC).
     */
    public static function for(WorkSession $session, TimesheetGrouping $grouping, string $timezone): string
    {
        $local = $session->start->copy()->setTimezone($timezone);

        return match ($grouping) {
            TimesheetGrouping::Project => $session->project?->name ?? __('No project'),
            TimesheetGrouping::Task => $session->task?->title ?? __('No task'),
            TimesheetGrouping::Day => $local->format('Y-m-d'),
            TimesheetGrouping::Week => __('Week of :date', ['date' => $local->copy()->startOfWeek()->format('Y-m-d')]),
            TimesheetGrouping::Month => $local->format('Y-m'),
            TimesheetGrouping::None => __('Total'),
        };
    }
}
