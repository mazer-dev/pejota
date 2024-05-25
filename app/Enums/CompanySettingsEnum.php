<?php

namespace App\Enums;

enum CompanySettingsEnum: string
{
    case CLIENT_PREFER_TRADENAME = 'clients.prefer_tradename';
    case TASKS_FILL_ACTUAL_START_DATE_WHEN_IN_PROGRESS = 'tasks.fill_actual_start_date_when_in_progress';
    case TASKS_FILL_ACTUAL_END_DATE_WHEN_CLOSED = 'tasks.fill_actual_end_date_when_closed';
}
