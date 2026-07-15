<?php

namespace App\Filament\App\Resources\ProjectResource\Pages;

use App\Enums\QuotaEnum;
use App\Filament\App\Resources\ProjectResource;
use App\Models\Project;
use App\Support\Entitlements;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => Entitlements::withinQuota(
                    QuotaEnum::ActiveProjects,
                    Project::activeCount(),
                )),
        ];
    }
}
