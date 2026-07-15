<?php

namespace App\Enums;

enum QuotaEnum: string
{
    case TasksPerMonth = 'tasks_per_month';
    case ActiveProjects = 'active_projects';
}
