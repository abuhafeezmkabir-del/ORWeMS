<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Beneficiary Reference
    |--------------------------------------------------------------------------
    |
    | Prefix used when generating stable internal beneficiary references.
    | References look like PREFIX-000001 (e.g. WICHOV-000001). The prefix is
    | configurable so programme identity is not hard-coded into models.
    | The numeric suffix is the zero-padded primary key and encodes no
    | personal characteristics (age, sex, state, diagnosis, religion).
    |
    */

    'beneficiary_ref_prefix' => env('ORWEMS_BENEFICIARY_PREFIX', 'WICHOV'),
    'beneficiary_ref_padding' => 6,

    /*
    |--------------------------------------------------------------------------
    | Household Code
    |--------------------------------------------------------------------------
    */

    'household_code_prefix' => env('ORWEMS_HOUSEHOLD_PREFIX', 'HH'),

    /*
    |------------------------------------------------------------------------
    | Security (Phase 3D)
    |------------------------------------------------------------------------
    |
    | Everything here is configuration rather than hard-coded policy, so the
    | repository never carries production-only values and local HTTP
    | development never breaks. Defaults are the local-development defaults;
    | the production settings live in .env (never committed) and are listed in
    | .env.example and docs/PHASE3D_SECURITY_HARDENING.md.
    |
    */

    'security' => [

        /*
        | HTTP → HTTPS redirect. Off by default so http://localhost keeps
        | working; set ORWEMS_FORCE_HTTPS=true on the production deployment.
        */
        'force_https' => (bool) env('ORWEMS_FORCE_HTTPS', false),

        /*
        | Strict-Transport-Security. Off by default: sending HSTS before the
        | site is genuinely served over HTTPS would lock browsers out of it.
        */
        'hsts' => [
            'enabled' => (bool) env('ORWEMS_HSTS_ENABLED', false),
            'max_age' => (int) env('ORWEMS_HSTS_MAX_AGE', 31536000),
            'preload' => (bool) env('ORWEMS_HSTS_PRELOAD', false),
        ],

        /*
        | Content-Security-Policy. Enabled by default. Turning it off is a
        | diagnostic measure, not a deployment recommendation; report-only mode
        | evaluates and reports the policy without blocking, which is the safe
        | way to roll a tightened policy out.
        */
        'csp' => [
            'enabled' => (bool) env('ORWEMS_CSP_ENABLED', true),
            'report_only' => (bool) env('ORWEMS_CSP_REPORT_ONLY', false),
        ],

        /*
        | Password expiry. OFF by default — enabling a policy WICHOV has not
        | agreed would lock operators out of a system they are entitled to use.
        |
        | When switched on, a password is considered expired `days` after the
        | last change. `password_changed_at` is stamped by every reset and by
        | the authenticated change-password flow, and accounts where it is still
        | null are grandfathered rather than expired (see
        | App\Http\Middleware\EnsurePasswordIsFresh for why).
        */
        'password_expiry' => [
            'enabled' => (bool) env('ORWEMS_PASSWORD_EXPIRY_ENABLED', false),
            'days' => (int) env('ORWEMS_PASSWORD_EXPIRY_DAYS', 180),
        ],

        /*
        | Second factor. NOT IMPLEMENTED — see docs/PHASE3D_SECURITY_HARDENING.md.
        |
        | This flag exists so the switch is visible, deliberate and testable,
        | and so nothing can silently begin demanding a second factor that no
        | code path can actually verify. It defaults to false and no code
        | honours it yet; a test asserts both facts.
        */
        'two_factor' => [
            'enabled' => (bool) env('ORWEMS_TWO_FACTOR_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demonstration Mode Configuration
    |--------------------------------------------------------------------------
    */

    'demo' => [

        'enabled' => filter_var(env('ORWEMS_DEMO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'email' => env('ORWEMS_DEMO_EMAIL', 'demo@wicov.org'),
        'password' => env('ORWEMS_DEMO_PASSWORD', 'demo123'),

    ],

];
