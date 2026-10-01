<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | ORWeMS does not ship a lang/ directory by default, so these strings are
    | provided explicitly. `auth.failed` is intentionally generic: it must never
    | reveal whether an account exists, is disabled, or the password was wrong,
    | because that would allow user enumeration against WICHOV staff accounts.
    |
    */

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

];
