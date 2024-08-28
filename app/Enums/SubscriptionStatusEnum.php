<?php

namespace App\Enums;

enum SubscriptionStatusEnum: string
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case CANCELED = 'canceled';
}
