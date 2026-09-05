<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Routing;

use Modufolio\Panel\Routing\ResourceBaseUrl;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The base URL comes from the routes, so a prefixed resource is found where
 * its routes actually are — and a resource routed without an index still
 * resolves, from whichever route sits below the base.
 */
final class ResourceBaseUrlTest extends TestCase
{
    /** @param array<string, string> $routes name => path */
    private function urls(array $routes): UrlGeneratorInterface
    {
        $collection = new RouteCollection();

        foreach ($routes as $name => $path) {
            $collection->add($name, new Route($path));
        }

        return new UrlGenerator($collection, new RequestContext());
    }

    public function testTheIndexRouteIsTheBase(): void
    {
        $urls = $this->urls(['movies' => '/admin/movies', 'movies_create' => '/admin/movies/create']);

        self::assertSame('/admin/movies', ResourceBaseUrl::resolve($urls, 'movies'));
    }

    public function testTheStoreRouteSharesTheIndexPath(): void
    {
        $urls = $this->urls(['movies_store' => '/admin/movies', 'movies_create' => '/admin/movies/create']);

        self::assertSame('/admin/movies', ResourceBaseUrl::resolve($urls, 'movies'));
    }

    public function testARouteBelowTheBaseHasItsTailCutOff(): void
    {
        self::assertSame(
            '/admin/movies',
            ResourceBaseUrl::resolve($this->urls(['movies_create' => '/admin/movies/create']), 'movies'),
        );
        self::assertSame(
            '/admin/movies',
            ResourceBaseUrl::resolve($this->urls(['movies_show' => '/admin/movies/{uuid}']), 'movies'),
        );
        self::assertSame(
            '/admin/movies',
            ResourceBaseUrl::resolve($this->urls(['movies_edit' => '/admin/movies/{uuid}/edit']), 'movies'),
        );
    }

    /**
     * A route whose path is not shaped as the loader shapes it says nothing
     * about the base; the next candidate is tried rather than a guess made.
     */
    public function testAnUnexpectedlyShapedRouteIsSkipped(): void
    {
        $urls = $this->urls([
            'movies_create' => '/admin/new-movie',
            'movies_show'   => '/admin/movies/{uuid}',
        ]);

        self::assertSame('/admin/movies', ResourceBaseUrl::resolve($urls, 'movies'));
    }

    /** Hand-written routes under other names keep the historical default. */
    public function testTheLoaderDefaultIsTheFallback(): void
    {
        self::assertSame('/panel/movies', ResourceBaseUrl::resolve($this->urls([]), 'movies'));
    }
}
