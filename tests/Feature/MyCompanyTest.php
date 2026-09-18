<?php

namespace Tests\Feature;

use App\Filament\App\Pages\MyCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class MyCompanyTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    public function test_saving_persists_the_company_document(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);

        Livewire::test(MyCompany::class)
            ->set('data.document', '12.345.678/0001-90')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('12.345.678/0001-90', $company->refresh()->document);
    }

    /**
     * O documento é opcional: nem toda empresa do core é brasileira, e o campo
     * é string livre — nenhum formato é imposto.
     */
    public function test_the_document_is_not_required(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);

        Livewire::test(MyCompany::class)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($company->refresh()->document);
    }

    public function test_the_saved_document_comes_back_on_the_form(): void
    {
        $user = User::factory()->create();
        $company = $this->actingInCompany($user);

        $company->update(['document' => '99.999.999/0001-99']);

        Livewire::test(MyCompany::class)
            ->assertSet('data.document', '99.999.999/0001-99');
    }
}
