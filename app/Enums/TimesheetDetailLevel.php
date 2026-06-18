<?php

namespace App\Enums;

enum TimesheetDetailLevel: string
{
    case Detailed = 'detailed';
    case GroupSummary = 'group_summary';
    case ParentTaskRollup = 'parent_task_rollup';
}
