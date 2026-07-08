<?php

namespace Tests\Feature\Assistant;

use App\Services\Ai\SchemaSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SchemaSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_describes_tables_columns_and_flags_tenant_tables(): void
    {
        $snapshot = app(SchemaSnapshotService::class)->snapshot();

        $this->assertStringContainsString('Tabela tasks (tenant: filtrar por company_id)', $snapshot);
        $this->assertStringContainsString('Tabela clients (tenant: filtrar por company_id)', $snapshot);
        $this->assertStringContainsString('- title', $snapshot);
        $this->assertStringContainsString('FK:', $snapshot);
        $this->assertStringNotContainsString('Tabela migrations', $snapshot);
        $this->assertStringNotContainsString('Tabela jobs', $snapshot);
    }

    public function test_it_caches_the_snapshot_and_forget_invalidates_it(): void
    {
        $service = app(SchemaSnapshotService::class);

        $original = $service->snapshot();

        Cache::forever($service->cacheKey(), 'SENTINEL');
        $this->assertSame('SENTINEL', $service->snapshot());

        $service->forget();
        $this->assertSame($original, $service->snapshot());
    }
}
