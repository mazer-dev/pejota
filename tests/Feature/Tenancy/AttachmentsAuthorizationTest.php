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
}
