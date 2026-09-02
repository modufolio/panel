<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Table\ChildTable;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Actor;
use Modufolio\Panel\Tests\Fixture\Entity\CastMember;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\MovieResource;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Child tables through a rendered listing: the schema carries them, the rows
 * ride on the presented parent row, and a relation the entity does not map
 * the way a child needs is refused where the metadata is.
 */
final class ChildTablesTest extends DoctrineTestCase
{
    /** Anonymous resources have no name a route config can hold; routes come from the named class. */
    private function movieRoutes(): UrlGeneratorInterface
    {
        return $this->urlGenerator(MovieResource::class);
    }

    /** A resource whose listing rows carry their cast, as the drawer's already do. */
    private function resourceWithCast(): MovieResource
    {
        return new class extends MovieResource {
            public function present(array $entities): array
            {
                return array_map(static function (object $movie): array {
                    assert($movie instanceof Movie);

                    return [
                        'id'    => $movie->getId(),
                        'title' => $movie->getTitle(),
                        'cast'  => array_map(static fn (CastMember $member): array => [
                            'id'        => $member->getUuid()->toString(),
                            'character' => $member->getCharacter(),
                            'actor'     => $member->getActor()?->getName(),
                        ], $movie->getCast()->toArray()),
                    ];
                }, $entities);
            }

            public function tableSchema(): TableSchema
            {
                return parent::tableSchema()->children([
                    ChildTable::relation('cast', 'Cast')
                        ->columns([Column::make('actor'), Column::make('character')])
                        ->recordUrl('/panel/movies/{parent}/cast/{id}')
                        ->empty('No cast listed.'),
                ]);
            }
        };
    }

    private function seedHeatWithCast(): void
    {
        $studio  = (new Studio())->setName('Warner Bros.');
        $pacino  = (new Actor())->setName('Al Pacino');
        $deNiro  = (new Actor())->setName('Robert De Niro');
        $heat    = (new Movie())->setTitle('Heat')->setStudio($studio);
        $empty   = (new Movie())->setTitle('Collateral')->setStudio($studio);

        // Persisted out of position order, so the order the row shows is the
        // association's OrderBy and not insertion luck.
        $heat->addCastMember((new CastMember())->setCharacter('Neil McCauley')->setActor($deNiro)->setPosition(1));
        $heat->addCastMember((new CastMember())->setCharacter('Vincent Hanna')->setActor($pacino)->setPosition(0));

        $this->persist($studio, $pacino, $deNiro, $heat, $empty);
        $this->clear();
    }

    public function testTheSchemaCarriesItsChildrenAndTheRowsCarryTheirRows(): void
    {
        $this->seedHeatWithCast();

        $props = $this->renderProps($this->listing($this->resourceWithCast(), urls: $this->movieRoutes()));

        self::assertSame([
            [
                'key'       => 'cast',
                'label'     => 'Cast',
                'relation'  => 'cast',
                'source'    => 'cast',
                'columns'   => $props['table']['children'][0]['columns'],
                'recordUrl' => '/panel/movies/{parent}/cast/{id}',
                'empty'     => 'No cast listed.',
            ],
        ], $props['table']['children']);

        self::assertSame(
            ['actor', 'character'],
            array_column($props['table']['children'][0]['columns'], 'key'),
        );
        self::assertSame(
            [false, false],
            array_column($props['table']['children'][0]['columns'], 'sortable'),
            'A child has no list query to derive sortability from.',
        );

        $rows = $props['movies']['data'];
        self::assertSame(['Collateral', 'Heat'], array_column($rows, 'title'));

        self::assertIsArray($rows[0]['cast']);
        self::assertSame([], $rows[0]['cast'], 'A parent without related rows carries an empty list, not a missing key.');

        self::assertIsArray($rows[1]['cast']);
        self::assertSame(
            ['Vincent Hanna', 'Neil McCauley'],
            array_column($rows[1]['cast'], 'character'),
            'Rows in the association\'s declared order.',
        );
        self::assertSame(['Al Pacino', 'Robert De Niro'], array_column($rows[1]['cast'], 'actor'));
    }

    /** Adding children changes nothing above the table: the prop contract holds. */
    public function testChildrenLiveInsideTheTablePropOnly(): void
    {
        $this->seedHeatWithCast();

        $props = $this->renderProps($this->listing($this->resourceWithCast(), urls: $this->movieRoutes()));

        self::assertSame(['filters', 'movies', 'stack', 'resource', 'table', 'auth', 'flash'], array_keys($props));
    }

    public function testASchemaWithoutChildrenSerialisesAnEmptyList(): void
    {
        $this->seedHeatWithCast();

        $props = $this->renderProps($this->listing(new MovieResource()));

        self::assertSame([], $props['table']['children']);
    }

    // ── Refusals ─────────────────────────────────────────────────────────────

    private function resourceWithChild(ChildTable $child): MovieResource
    {
        return new class($child) extends MovieResource {
            public function __construct(private readonly ChildTable $child)
            {
            }

            public function tableSchema(): TableSchema
            {
                return parent::tableSchema()->children([$this->child]);
            }
        };
    }

    public function testAnUnmappedRelationIsRefusedByName(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TableSchema children name "reviews", but ' . Movie::class . ' maps no such association.');

        $this->renderProps($this->listing(
            $this->resourceWithChild(ChildTable::relation('reviews', 'Reviews')),
            urls: $this->movieRoutes(),
        ));
    }

    public function testAToOneRelationIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('maps it as a to-one association; a child table lists a collection');

        $this->renderProps($this->listing(
            $this->resourceWithChild(ChildTable::relation('studio', 'Studio')),
            urls: $this->movieRoutes(),
        ));
    }

    /**
     * A many-to-many would load once per parent row. Refused rather than
     * quietly paid for: a bound the panel imposes must be visible.
     */
    public function testAManyToManyRelationIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('maps it many-to-many, which would load once per row');

        $this->renderProps($this->listing(
            $this->resourceWithChild(ChildTable::relation('tags', 'Tags')),
            urls: $this->movieRoutes(),
        ));
    }
}
