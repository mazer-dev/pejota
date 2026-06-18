<?php

namespace App\Services\Timesheet\Layouts;

use App\Services\Timesheet\TimesheetRequest;

interface TimesheetLayout
{
    public function key(): string;

    public function label(): string;

    /**
     * Column definitions: list of ['key' => string, 'label' => string, 'type' => 'date'|'duration'|'money'|'text'].
     * Money/rate columns must only be present when $request->includeValue is true.
     *
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public function columns(TimesheetRequest $request): array;

    public function headerView(): ?string;
}
