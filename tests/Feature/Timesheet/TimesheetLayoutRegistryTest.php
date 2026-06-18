<?php

namespace Tests\Feature\Timesheet;

use App\Services\Timesheet\Layouts\ClientTimesheetLayout;
use App\Services\Timesheet\TimesheetLayoutRegistry;
use Tests\TestCase;

class TimesheetLayoutRegistryTest extends TestCase
{
    public function test_registry_lists_registered_layouts(): void
    {
        $registry = app(TimesheetLayoutRegistry::class);

        $keys = array_keys($registry->all());
        $this->assertContains('client', $keys);
        $this->assertContains('internal', $keys);
    }

    public function test_get_returns_layout_by_key(): void
    {
        $registry = app(TimesheetLayoutRegistry::class);

        $this->assertSame('internal', $registry->get('internal')->key());
    }

    public function test_get_falls_back_to_client_for_unknown_key(): void
    {
        $registry = app(TimesheetLayoutRegistry::class);

        $this->assertInstanceOf(ClientTimesheetLayout::class, $registry->get('does-not-exist'));
    }
}
