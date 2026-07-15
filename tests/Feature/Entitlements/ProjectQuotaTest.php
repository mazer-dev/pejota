<?php

namespace Tests\Feature\Entitlements;

use App\Contracts\FeatureGate;
use App\Enums\FeatureEnum;
use App\Enums\QuotaEnum;
use App\Filament\App\Resources\ProjectResource\Pages\CreateProject;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class ProjectQuotaTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private function actInCompany(): void
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Acme', 'email' => 'acme@example.test', 'user_id' => $user->id]);
        $user->companies()->attach($company->id, ['joined_at' => now()]);
        $this->actingInCompany($user, $company);
    }

    private function bindLimit(?int $limit): void
    {
        $this->app->bind(FeatureGate::class, fn () => new class($limit) implements FeatureGate
        {
            public function __construct(private ?int $limit) {}

            public function allows(Company $company, FeatureEnum $feature): bool
            {
                return true;
            }

            public function limitFor(Company $company, QuotaEnum $quota): ?int
            {
                return $quota === QuotaEnum::ActiveProjects ? $this->limit : null;
            }
        });
    }

    public function test_count_only_counts_active_projects(): void
    {
        $this->actInCompany();

        Project::create(['name' => 'Active', 'active' => true]);
        Project::create(['name' => 'Inactive', 'active' => false]);

        $this->assertSame(1, Project::activeCount());
    }

    public function test_create_is_blocked_when_active_projects_at_limit(): void
    {
        $this->actInCompany();
        $this->bindLimit(3);
        Project::create(['name' => 'P1', 'active' => true]);
        Project::create(['name' => 'P2', 'active' => true]);
        Project::create(['name' => 'P3', 'active' => true]); // at limit = 3

        Livewire::test(CreateProject::class)
            ->fillForm(['name' => 'Fourth'])
            ->call('create')
            ->assertNotified();

        $this->assertSame(3, Project::count());
    }

    public function test_create_passes_when_under_limit(): void
    {
        $this->actInCompany();
        $this->bindLimit(5);

        Livewire::test(CreateProject::class)
            ->fillForm(['name' => 'Under'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Project::count());
    }
}
