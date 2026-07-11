<!DOCTYPE html>
<html>
<body style="font-family: ui-sans-serif, system-ui, sans-serif; color: #111827;">
    <h2>{{ __('You have been invited to :company', ['company' => $invitation->company->name]) }}</h2>

    <p>{{ __('You were invited to join :company on Pejota as :role.', ['company' => $invitation->company->name, 'role' => __(ucfirst($invitation->role->value))]) }}</p>

    <p>
        <a href="{{ $acceptUrl }}"
           style="display:inline-block;padding:10px 18px;background:#00BF63;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">
            {{ __('Accept invitation') }}
        </a>
    </p>

    <p style="color:#6b7280;font-size:14px;">
        {{ __('Or paste this link into your browser:') }}<br>
        {{ $acceptUrl }}
    </p>

    <p style="color:#6b7280;font-size:14px;">
        {{ __('This invitation expires on :date.', ['date' => $invitation->expires_at->toDayDateTimeString()]) }}
    </p>
</body>
</html>
