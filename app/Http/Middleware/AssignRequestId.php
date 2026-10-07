<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request a correlation identifier and echoes it on the response.
 *
 * The identifier is what ties a line in the application log, an audit row, a
 * user's report of "it failed at about this time" and the eventual support
 * ticket back to one concrete HTTP request. Without it, investigating a
 * incident means guessing which rows belong together.
 *
 * Trust rules, because the value arrives from outside:
 *
 *  - an inbound `X-Request-Id` is used only when it matches a conservative
 *    shape (`[A-Za-z0-9._-]{8,64}`); anything else is discarded and replaced
 *    with a generated UUID, so a caller cannot inject newlines, markup or an
 *    unbounded string into the audit table or the logs;
 *  - when there is no inbound header, a UUID v4 is generated.
 *
 * Registered globally in bootstrap/app.php so it also covers responses that
 * never reach a controller (redirects, 404s, CSRF rejections), which are
 * exactly the responses an investigator most needs to correlate.
 */
class AssignRequestId
{
    /** Request attribute holding the identifier. */
    public const ATTRIBUTE = '_orwems_request_id';

    /** Response/request header name. */
    public const HEADER = 'X-Request-Id';

    private const ACCEPTABLE = '/^[A-Za-z0-9._-]{8,64}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $supplied = (string) $request->header(self::HEADER, '');

        $id = preg_match(self::ACCEPTABLE, $supplied) === 1
            ? $supplied
            : (string) \Illuminate\Support\Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $id);

        $response = $next($request);

        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}