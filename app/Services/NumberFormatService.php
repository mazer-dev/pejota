<?php

namespace App\Services;

use App\Enums\CompanySettingsEnum;

class NumberFormatService
{
    public function getNextNumber(CompanySettingsEnum $companySetting): int
    {
        $number = auth()->user()->company->settings()
            ->get(
                $companySetting->value,
                0
            );

        $number++;

        auth()->user()->company->settings()
            ->set(
                $companySetting->value,
                $number
            );

        return $number;
    }
}
