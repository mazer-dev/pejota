<?php

namespace App\Livewire;

use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class AcceptInvitation extends Component
{
    public string $token = '';

    public string $state = 'invalid';

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->state = $this->resolveState();
    }

    #[Computed]
    public function invitation(): ?Invitation
    {
        return Invitation::where('token', $this->token)->first();
    }

    private function resolveState(): string
    {
        $invitation = $this->invitation();

        if ($invitation === null || ! $invitation->isPending()) {
            return 'invalid';
        }

        $existing = User::where('email', $invitation->email)->first();

        if ($existing === null) {
            return 'new-user';
        }

        if (Auth::check() && Auth::id() === $existing->id) {
            return 'confirm';
        }

        if (Auth::check()) {
            return 'mismatch';
        }

        return 'login';
    }

    public function acceptExisting(InvitationService $service): void
    {
        abort_unless($this->resolveState() === 'confirm', 403);

        $service->accept($this->invitation(), Auth::user());

        $this->redirect($this->companyUrl(), navigate: false);
    }

    public function register(InvitationService $service): void
    {
        abort_unless($this->resolveState() === 'new-user', 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $service->acceptAsNewUser($this->invitation(), $this->name, $this->password);

        Auth::login($user);

        $this->redirect($this->companyUrl(), navigate: false);
    }

    public function signIn(): void
    {
        session()->put('url.intended', route('invitations.accept', $this->token));

        $this->redirect(Filament::getPanel('app')->getLoginUrl(), navigate: false);
    }

    private function companyUrl(): string
    {
        $company = $this->invitation()->company;

        return Filament::getPanel('app')->getUrl($company)
            ?? url('/app/'.$company->getKey());
    }

    public function render(): View
    {
        return view('livewire.accept-invitation');
    }
}
