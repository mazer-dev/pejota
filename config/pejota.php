<?php

return [
    'registration_page' => env('PEJOTA_REGISTRATION_PAGE'),

    /*
     * Days until an emailed invitation expires.
     */
    'invitation_expires_after_days' => (int) env('PEJOTA_INVITATION_EXPIRES_DAYS', 7),
];
