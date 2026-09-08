<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Resource;

use Modufolio\Panel\Resource\RelationAddUrls;
use Modufolio\Panel\Tests\Fixture\CastDrawerMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Where a new row goes, stamped onto the lists that can take one.
 *
 * The tabs describe the record; the URL belongs to the record's own resource,
 * which is the whole point — a frame stacked over another resource's record
 * used to have its add action composed from the page underneath it.
 */
final class RelationAddUrlsTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>> $tabs
     * @return list<array<string, mixed>>
     */
    private function stamp(array $tabs, UrlGeneratorInterface $urls): array
    {
        return (new RelationAddUrls($urls))->stamp($tabs, new CastDrawerMovieResource(), new Movie(), null);
    }

    private function generator(?string $generated = '/panel/movies/u-1/relations/cast'): UrlGeneratorInterface
    {
        return new class ($generated) implements UrlGeneratorInterface {
            public function __construct(private readonly ?string $generated) {}

            /** @param array<string, mixed> $parameters */
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                if ($this->generated === null) {
                    throw new RouteNotFoundException($name);
                }

                return $this->generated . '?' . http_build_query(['route' => $name, ...$parameters]);
            }

            public function setContext(RequestContext $context): void {}

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }
        };
    }

    /** @return list<array<string, mixed>> */
    private function tabs(): array
    {
        return [[
            'key'      => 'details',
            'type'     => 'details',
            'sections' => [
                ['key' => 'cast', 'addable' => true, 'addTarget' => 'cast'],
                ['key' => 'remakes', 'addable' => false],
            ],
        ], [
            'key'       => 'tags',
            'type'      => 'relation',
            'addable'   => true,
            'addTarget' => 'tags',
        ]];
    }

    public function testItStampsEveryAddableListAtEitherLevel(): void
    {
        $tabs = $this->stamp($this->tabs(), $this->generator());

        self::assertStringContainsString('route=movies_relation_store', $tabs[0]['sections'][0]['addUrl']);
        self::assertStringContainsString('field=cast', $tabs[0]['sections'][0]['addUrl']);
        self::assertStringContainsString('field=tags', $tabs[1]['addUrl']);
    }

    public function testItLeavesNonAddableListsAlone(): void
    {
        $tabs = $this->stamp($this->tabs(), $this->generator());

        self::assertArrayNotHasKey('addUrl', $tabs[0]['sections'][1]);
        self::assertFalse($tabs[0]['sections'][1]['addable']);
    }

    /**
     * A resource that generates no form routes has no endpoint to post to.
     * Withdrawing the offer is the point: a "+ Add" that 404s is worse than
     * no "+ Add" at all.
     */
    public function testAListWithNoRouteIsNoLongerAddable(): void
    {
        $tabs = $this->stamp($this->tabs(), $this->generator(null));

        self::assertFalse($tabs[0]['sections'][0]['addable']);
        self::assertNull($tabs[0]['sections'][0]['addUrl']);
        self::assertFalse($tabs[1]['addable']);
    }

    /** A list the declaration never made addable is untouched, URL or not. */
    public function testAnAddableListWithNoTargetIsNotAddable(): void
    {
        $tabs = $this->stamp([['key' => 'cast', 'addable' => true, 'addTarget' => null]], $this->generator());

        self::assertFalse($tabs[0]['addable']);
        self::assertNull($tabs[0]['addUrl']);
    }
}
