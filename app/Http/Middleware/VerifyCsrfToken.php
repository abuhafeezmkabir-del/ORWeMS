<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as BaseVerifier;

/**
 * Extended CSRF verification that exempts demo authentication routes.
 * This is needed because demo mode uses environment-based credentials
 * and the login POST needs to work without CSRF tokens for demo purposes.
 */
class VerifyCsrfToken extends BaseVerifier
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [];
}