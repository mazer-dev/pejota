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
];
