<?php

declare(strict_types=1);

namespace Modufolio\Panel\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Where a resource lives, asked of the router rather than built from its key.
 *
 * The client derives every write URL from this one path — `{base}/create`,
 * `{base}/{id}/edit`, `{base}/{id}/board-move` — so it has to be the path the
 * routes were actually generated under. `->prefix('/admin')` moves a resource
 * off the loader's default, and a string-built `/panel/{key}` sent the client
 * to pages that did not exist.
 */
final class ResourceBaseUrl
{
    /** A well-formed UUID no record has: enough to make `{uuid}` routes generate. */
    private const SENTINEL = '00000000-0000-4000-8000-000000000000';

    /**
     * The index route is the base itself, and the store route shares its path.
     * A resource routed without either — `only(['edit'])` — still has its
     * base one segment above whichever route it does generate, so those are
     * tried next, each with the part below the base cut off.
     *
     * @var array<string, string> route-name suffix => the path below the base
     */
    private const BELOW_BASE = [
        ''         => '',
        '_store'   => '',
        '_create'  => '/create',
        '_show'    => '/' . self::SENTINEL,
        '_update'  => '/' . self::SENTINEL,
        '_destroy' => '/' . self::SENTINEL,
        '_edit'    => '/' . self::SENTINEL . '/edit',
    ];

    /**
     * The path the resource's routes were generated under, or the loader's
     * default when the router knows none of them — a resource whose routes
     * are all hand-written and named differently keeps working as before.
     */
    public static function resolve(UrlGeneratorInterface $urls, string $key): string
    {
        foreach (self::BELOW_BASE as $suffix => $below) {
            // Only the routes with a `{uuid}` get one: an unused parameter
            // would come back as a query string on the base.
            $parameters = str_contains($below, self::SENTINEL) ? ['uuid' => self::SENTINEL] : [];

            try {
                $url = $urls->generate($key . $suffix, $parameters);
            } catch (\Throwable) {
                continue;
            }

            if ($below === '') {
                return $url;
            }

            if (str_ends_with($url, $below)) {
                return substr($url, 0, -strlen($below));
            }
        }

        return '/panel/' . $key;
    }
}
