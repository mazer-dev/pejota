<?php

namespace Tests\Feature;

use App\Filament\App\Resources\NoteResource;
use App\Models\Company;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsInCompany;
use Tests\TestCase;

class ViewNotePageTest extends TestCase
{
    use ActsInCompany, RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->actingInCompany($this->user);
    }

    public function test_view_note_page_renders_the_infolist(): void
    {
        $note = Note::create([
            'title' => 'Quarterly planning',
            'content' => [],
            'company_id' => $this->company->id,
        ]);

        $this->get(NoteResource::getUrl('view', ['record' => $note]))
            ->assertOk()
            ->assertSee('Quarterly planning');
    }
}
