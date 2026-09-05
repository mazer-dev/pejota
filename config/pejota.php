<?php

return [
    'registration_page' => env('PEJOTA_REGISTRATION_PAGE'),

    /*
     * Days until an emailed invitation expires.
     */
    'invitation_expires_after_days' => (int) env('PEJOTA_INVITATION_EXPIRES_DAYS', 7),

    /*
     * Filament plugin class-strings registered into the `app` panel.
     * Empty in open-core; the cloud overlay injects its billing plugin here.
     */
    'app_panel_plugins' => [],

    /*
     * Schema components appended to the tabs of the company settings page, keyed
     * by tab name - the string passed to `Tab::make()`, not the translated label.
     * Each value is a list of class-strings implementing
     * `App\Contracts\CompanySettingsComponents`. Empty in open-core; the cloud
     * overlay injects its own company settings here.
     */
    'company_settings_components' => [],

    /*
     * Class-string invokable `fn(Company $tenant, User $user): ?string` used to
     * redirect a blocked tenant instead of the Filament default 404. Null in
     * open-core (no-op); the cloud overlay points this at its billing landing.
     */
    'blocked_tenant_redirect' => null,
];
