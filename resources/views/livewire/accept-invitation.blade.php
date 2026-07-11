<div>
    @php($invitation = $this->invitation())

    @if ($this->state === 'invalid')
        <h1>{{ __('Invitation unavailable') }}</h1>
        <p class="muted">{{ __('This invitation is invalid, expired, or has already been accepted.') }}</p>

    @elseif ($this->state === 'new-user')
        <h1>{{ __('Join :company', ['company' => $invitation->company->name]) }}</h1>
        <p class="muted">{{ __('Create your account to accept this invitation.') }}</p>

        <form wire:submit="register">
            <label for="name">{{ __('Your name') }}</label>
            <input id="name" type="text" wire:model="name">
            @error('name') <div class="error">{{ $message }}</div> @enderror

            <label for="password">{{ __('Password') }}</label>
            <input id="password" type="password" wire:model="password">
            @error('password') <div class="error">{{ $message }}</div> @enderror

            <label for="password_confirmation">{{ __('Confirm password') }}</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation">

            <button class="btn" type="submit">{{ __('Create account & join') }}</button>
        </form>

    @elseif ($this->state === 'confirm')
        <h1>{{ __('Join :company', ['company' => $invitation->company->name]) }}</h1>
        <p class="muted">{{ __('You were invited to join as :role.', ['role' => __(ucfirst($invitation->role->value))]) }}</p>
        <button class="btn" wire:click="acceptExisting">{{ __('Accept invitation') }}</button>

    @elseif ($this->state === 'mismatch')
        <h1>{{ __('Wrong account') }}</h1>
        <p class="muted">{{ __('This invitation is for :email. Sign out and reopen this link to accept it.', ['email' => $invitation->email]) }}</p>

    @else
        <h1>{{ __('Sign in to accept') }}</h1>
        <p class="muted">{{ __('This invitation is for an existing account. Please sign in to accept it.') }}</p>
        <button class="btn" wire:click="signIn">{{ __('Sign in') }}</button>
    @endif
</div>
