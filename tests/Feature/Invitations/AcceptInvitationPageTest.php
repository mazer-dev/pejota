<?php

namespace Tests\Feature\Invitations;

use App\Enums\CompanyRoleEnum;
use App\Livewire\AcceptInvitation;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AcceptInvitationPageTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        $owner = User::factory()->create();

        return $owner->companies()->wherePivotNotNull('joined_at')->firstOrFail();
    }

    private function invitation(Company $company, string $email, CompanyRoleEnum $role = CompanyRoleEnum::Member): Invitation
    {
        return Invitation::create([
            'company_id' => $company->id, 'email' => $email, 'role' => $role,
            'token' => 'tok-'.$email, 'expires_at' => now()->addDay(),
        ]);
    }

    public function test_route_is_public(): void
    {
        $company = $this->company();
        $invitation = $this->invitation($company, 'newbie@x.com');

        $this->get(route('invitations.accept', $invitation->token))->assertOk();
    }

    public function test_invalid_token_renders_invalid_state(): void
    {
        Livewire::test(AcceptInvitation::class, ['token' => 'does-not-exist'])
            ->assertSet('state', 'invalid');
    }

    public function test_new_user_registers_and_joins(): void
    {
        $company = $this->company();
        $invitation = $this->invitation($company, 'newbie@x.com', CompanyRoleEnum::Member);
        $this->actingAs($company->owner);
        $expectedUrl = Filament::getPanel('app')->getUrl($company);

        Livewire::test(AcceptInvitation::class, ['token' => $invitation->token])
            ->assertSet('state', 'new-user')
            ->set('name', 'Newbie')
            ->set('password', 'secret-pass-123')
            ->set('password_confirmation', 'secret-pass-123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect($expectedUrl);

        $user = User::where('email', 'newbie@x.com')->firstOrFail();
        $this->assertTrue($company->hasMember($user));
        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $this->assertTrue($user->fresh()->hasRole(CompanyRoleEnum::Member->value));
    }

    public function test_existing_logged_in_user_confirms_and_joins(): void
    {
        $company = $this->company();
        $invitee = User::factory()->create();
        $invitation = $this->invitation($company, $invitee->email, CompanyRoleEnum::Admin);

        $this->actingAs($invitee);
        $expectedUrl = Filament::getPanel('app')->getUrl($company);

        Livewire::test(AcceptInvitation::class, ['token' => $invitation->token])
            ->assertSet('state', 'confirm')
            ->call('acceptExisting')
            ->assertRedirect($expectedUrl);

        $this->assertTrue($company->hasMember($invitee));
    }

    public function test_existing_user_not_logged_in_sees_login_state(): void
    {
        $company = $this->company();
        $invitee = User::factory()->create();
        $invitation = $this->invitation($company, $invitee->email);

        Livewire::test(AcceptInvitation::class, ['token' => $invitation->token])
            ->assertSet('state', 'login');
    }

    public function test_different_logged_in_user_sees_mismatch_state(): void
    {
        $company = $this->company();
        $invitee = User::factory()->create();
        $invitation = $this->invitation($company, $invitee->email);

        $other = User::factory()->create();
        $this->actingAs($other);

        Livewire::test(AcceptInvitation::class, ['token' => $invitation->token])
            ->assertSet('state', 'mismatch');
    }

    public function test_sign_in_action_sets_intended_and_redirects_to_login(): void
    {
        $company = $this->company();
        $invitee = User::factory()->create();
        $invitation = $this->invitation($company, $invitee->email);

        $loginUrl = Filament::getPanel('app')->getLoginUrl();

        Livewire::test(AcceptInvitation::class, ['token' => $invitation->token])
            ->assertSet('state', 'login')
            ->call('signIn')
            ->assertRedirect($loginUrl);

        $this->assertSame(route('invitations.accept', $invitation->token), session('url.intended'));
    }
}
