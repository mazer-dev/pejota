<?php

namespace App\Enums;

enum TimesheetGrouping: string
{
    case Project = 'project';
    case Task = 'task';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case None = 'none';
}
