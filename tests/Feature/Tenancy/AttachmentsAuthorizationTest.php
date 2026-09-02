<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_download_their_company_attachment(): void
    {
        Storage::fake('tasks');

        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        Storage::disk('tasks')->put($company->id.'/report.pdf', 'contents');

        $response = $this->actingAs($user)->get(
            route('attachments.get', [
                'module' => 'tasks',
                'companyId' => $company->id,
                'fileName' => 'report.pdf',
            ])
        );

        $response->assertOk();
    }

    public function test_non_member_cannot_download_another_companys_attachment(): void
    {
        Storage::fake('tasks');

        $user = User::factory()->create();
        $owner = User::factory()->create();
        $foreignCompany = $owner->companies()->firstOrFail();

        Storage::disk('tasks')->put($foreignCompany->id.'/report.pdf', 'contents');

        $response = $this->actingAs($user)->get(
            route('attachments.get', [
                'module' => 'tasks',
                'companyId' => $foreignCompany->id,
                'fileName' => 'report.pdf',
            ])
        );

        $response->assertNotFound();
    }

    public function test_member_can_download_their_company_logo(): void
    {
        Storage::fake('companies-logo');

        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        Storage::disk('companies-logo')->put($company->id.'/logo.png', 'image');

        $response = $this->actingAs($user)->get(
            route('attachments.get', [
                'module' => 'companies-logo',
                'companyId' => $company->id,
                'fileName' => 'logo.png',
            ])
        );

        $response->assertOk();
    }

    public function test_non_member_cannot_download_another_companys_logo(): void
    {
        Storage::fake('companies-logo');

        $user = User::factory()->create();
        $owner = User::factory()->create();
        $foreignCompany = $owner->companies()->firstOrFail();

        Storage::disk('companies-logo')->put($foreignCompany->id.'/logo.png', 'image');

        $response = $this->actingAs($user)->get(
            route('attachments.get', [
                'module' => 'companies-logo',
                'companyId' => $foreignCompany->id,
                'fileName' => 'logo.png',
            ])
        );

        $response->assertNotFound();
    }

    public function test_unconfigured_disk_names_are_rejected(): void
    {
        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        $response = $this->actingAs($user)->get(
            route('attachments.get', [
                'module' => 'local',
                'companyId' => $company->id,
                'fileName' => 'report.pdf',
            ])
        );

        $response->assertNotFound();
    }

    public function test_attachment_names_containing_path_separators_are_rejected(): void
    {
        Storage::fake('tasks');

        $user = User::factory()->create();
        $company = $user->companies()->firstOrFail();

        Storage::disk('tasks')->put($company->id.'/nested/report.pdf', 'contents');

        $response = $this->actingAs($user)->get(
            "/attachments/tasks/{$company->id}/nested%5Creport.pdf"
        );

        $response->assertNotFound();
    }
}
