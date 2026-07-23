<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication
    |--------------------------------------------------------------------------
    |
    | Set TWO_FACTOR_ENABLED=true in .env to re-enable 2FA UI and login checks.
    |
    */
    'two_factor_enabled' => (bool) env('TWO_FACTOR_ENABLED', false),
];
