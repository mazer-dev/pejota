<?php

namespace App\Enums;

enum ExchangeRateSourceEnum: string
{
    case Manual = 'manual';
    case Api = 'api';
}
