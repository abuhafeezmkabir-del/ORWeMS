<?php

namespace App\Support\Authorization;

use App\Models\Permission;
use Illuminate\Support\Facades\Cache;

/**
 * The permission vocabulary ORWeMS authorizes against.
 *
 * Laravel's Gate is excellent at answering "may this user do X?" but it has no
 * idea which abilities exist. Left alone it treats every unknown ability as
 * simply un-granted, and a typo in a route (`beneficiaries.vew`) becomes an
 * ordinary denial that is indistinguishable from a missing role — a silent
 * misconfiguration rather than a visible one.
 *
 * This class supplies the missing half: the set of codes the system recognises.
 * It lets the Gate distinguish "this ability is not part of the system" from
 * "this account does not hold it", so a misconfigured route is caught by a test
 * instead of by a user clicking a link that mysteriously fails.
 *
 * Codes are cached for a short period because they change rarely and are read
 * on every authorization check. The cache is a performance detail only: every
 * decision still comes from live role and permission rows, so revoking a
 * privilege takes effect at once and only the vocabulary listing can be briefly
 * stale.
 */
final class PermissionCatalog
{
    /**
     * Seconds the recognised-code list is cached for. Short enough that a newly
     * seeded permission becomes usable almost immediately, long enough that an
     * authorization check does not query the vocabulary on every request.
     */
    private const CACHE_TTL_SECONDS = 300;

    private const CACHE_KEY = 'orwems.authorization.permission_codes';

    /**
     * Every permission code ORWeMS recognises, active or not.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        // Tests bypass the cache entirely. The array cache store lives for the
        // whole PHP process, so a code list cached by one test would leak into
        // the next one and make results depend on execution order. Production
        // and development keep the cache, where it is a genuine saving.
        if (app()->environment('testing')) {
            return self::readCodes();
        }

        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            static fn (): array => self::readCodes()
        );
    }

    /**
     * Read the code list straight from the database.
     *
     * @return list<string>
     */
    private static function readCodes(): array
    {
        return Permission::query()
            ->orderBy('code')
            ->pluck('code')
            ->all();
    }

    /**
     * Whether a code belongs to the ORWeMS permission vocabulary.
     *
     * Callers use this to FAIL LOUDLY on an ability that was never defined,
     * rather than silently denying a legitimate action or silently permitting a
     * misspelled one.
     */
    public static function recognises(string $code): bool
    {
        return in_array($code, self::codes(), true);
    }

    /**
     * Discard the cached vocabulary.
     *
     * Called after permissions are seeded, edited or deactivated so an
     * administrative change takes effect at once instead of after the TTL.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}