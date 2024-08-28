<?php

namespace App\Enums;

enum MenuSortEnum: int
{
    case CLIENTS = 0;
    case VENDORS = 5;
    case PROJECTS = 10;
    case CONTRACTS = 20;
    case TASKS = 30;
    case WORK_SESSIONS = 40;
    case NOTES = 50;
    case SUBSCRIPTIONS = 60;
    case SETTINGS = 70;
}
