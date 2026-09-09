<?php

declare(strict_types=1);

namespace Modufolio\Panel\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The router asked about a resource's generated routes, without the caller
 * having to catch anything.
 *
 * A resource declares which routes it wants — `only(['index', 'show'])` — so
 * the absence of a route is a normal answer here, not an error: a listing
 * offers no edit button when no edit route was generated. The generator
 * signals that by throwing, which is why every one of these swallows and
 * reports null or false instead.
 *
 * Route existence is asked of the router rather than derived from the
 * resource's own config, because the router is where a hand-written route or
 * a `->prefix()` actually lands — the two disagreeing is how the client came
 * to be sent URLs that 404.
 */
final class RouteUrls
{
    /**
     * A well-formed UUID no record has, so a `{uuid}` route generates without
     * a record in hand — enough to ask whether it exists, or to build the
     * template below.
     */
    public const SENTINEL = '00000000-0000-4000-8000-000000000000';

    /**
     * A route's URL, or null when it was not generated.
     *
     * @param array<string, mixed> $parameters
     */
    public static function url(UrlGeneratorInterface $urls, string $name, array $parameters = []): ?string
    {
        try {
            return $urls->generate($name, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Whether the route exists, record routes included. */
    public static function exists(UrlGeneratorInterface $urls, string $name): bool
    {
        return self::url($urls, $name, ['uuid' => self::SENTINEL]) !== null;
    }

    /**
     * A record route as a URL template with `{id}` where its uuid goes, for a
     * client that has the id but not the path.
     *
     * Generated with the sentinel and substituted rather than string-built:
     * the router is the authority on where a route lives, and a hand-built
     * path was already wrong once for `users_export`.
     */
    public static function template(UrlGeneratorInterface $urls, string $name): ?string
    {
        $url = self::url($urls, $name, ['uuid' => self::SENTINEL]);

        return $url === null ? null : str_replace(self::SENTINEL, '{id}', $url);
    }
}
