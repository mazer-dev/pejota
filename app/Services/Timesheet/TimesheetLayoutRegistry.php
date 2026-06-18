<?php

namespace App\Services\Timesheet;

use App\Services\Timesheet\Layouts\TimesheetLayout;

class TimesheetLayoutRegistry
{
    /**
     * @var array<string, TimesheetLayout>
     */
    private array $layouts = [];

    public function register(TimesheetLayout $layout): void
    {
        $this->layouts[$layout->key()] = $layout;
    }

    /**
     * @return array<string, TimesheetLayout>
     */
    public function all(): array
    {
        return $this->layouts;
    }

    public function get(string $key): TimesheetLayout
    {
        return $this->layouts[$key] ?? $this->layouts['client'];
    }
}
