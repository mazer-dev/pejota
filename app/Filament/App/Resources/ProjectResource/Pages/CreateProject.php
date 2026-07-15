<?php

namespace App\Filament\App\Resources\ProjectResource\Pages;

use App\Enums\QuotaEnum;
use App\Filament\App\Concerns\EnforcesCreateQuota;
use App\Filament\App\Resources\ProjectResource;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    use EnforcesCreateQuota;

    protected static string $resource = ProjectResource::class;

    protected function quotaKey(): QuotaEnum
    {
        return QuotaEnum::ActiveProjects;
    }

    protected function currentQuotaCount(): int
    {
        return Project::activeCount();
    }
}
