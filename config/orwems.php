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

];
