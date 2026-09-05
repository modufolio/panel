<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Resource\RecordPresenter;
use Modufolio\Panel\Table\Column;
use Modufolio\Panel\Table\TableSchema;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\DerivedMovieResource;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;
use Modufolio\Panel\Tests\Fixture\Entity\Studio;
use Modufolio\Panel\Tests\Fixture\MovieResource;

/**
 * A resource without a presenter gets its rows read off the entity: what a
 * column names is what the cell shows, through the same query language a
 * Kirby blueprint uses.
 */
final class RecordPresenterTest extends DoctrineTestCase
{
    private function heat(?Studio $studio = null): Movie
    {
        return (new Movie())
            ->setTitle('Heat')
            ->setYear(1995)
            ->setRating('8.3')
            ->setReleased(true)
            ->setReleasedOn(new \DateTimeImmutable('1995-12-15'))
            ->setStudio($studio)
            ->setSynopsis('A thief and a cop.');
    }

    public function testARowIsTheIdAndEveryColumnResolvedAgainstTheEntity(): void
    {
        $movie = $this->heat((new Studio())->setName('Warner Bros.'));

        $row = (new RecordPresenter(new DerivedMovieResource()))->rows([$movie])[0];

        self::assertSame($movie->getUuid()->toString(), $row['id'], 'The public id is the uuid.');
        self::assertSame('Heat', $row['title']);
        self::assertSame(1995, $row['year']);
        self::assertSame('8.3', $row['rating']);
        self::assertSame('Warner Bros.', $row['studio'], 'The value() path is resolved into the column\'s own key.');
    }

    public function testANullAlongAPathIsANullValueNotAnError(): void
    {
        $row = (new RecordPresenter(new DerivedMovieResource()))->rows([$this->heat(null)])[0];

        self::assertNull($row['studio']);
    }

    public function testATemplateColumnRendersOverTheEntity(): void
    {
        $resource = new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return TableSchema::make()->columns([
                    Column::make('headline')->text('{{ movie.title }} ({{ movie.year }})'),
                    Column::make('opened')->text('{{ record.released_on.format("d-m-Y") }}'),
                ]);
            }
        };

        $row = (new RecordPresenter($resource))->rows([$this->heat()])[0];

        self::assertSame('Heat (1995)', $row['headline'], 'The singular key is a root.');
        self::assertSame('15-12-1995', $row['opened'], '`record` is always a root, and snake_case reaches the accessor.');
    }

    /** The drawer's record adds the form's keys, so the drawer can show what the form edits. */
    public function testTheRecordAddsTheFormKeysToTheRow(): void
    {
        $record = new DerivedMovieResource()->presentOne($this->heat());

        self::assertSame('A thief and a cop.', $record['synopsis']);
        self::assertSame('1995-12-15T00:00:00+00:00', $record['released_on'], 'Dates travel as ISO 8601.');
        self::assertArrayHasKey('studio', $record, 'The columns are still there.');
    }

    /** A `{relation}_id` form key reads back the related record's public id, as the lookup control round-trips it. */
    public function testARelationFormKeyReadsBackTheRelatedId(): void
    {
        $studio   = (new Studio())->setName('Warner Bros.');
        $resource = new class extends DerivedMovieResource {
            public function form(): \Modufolio\Panel\Form\Form
            {
                return \Modufolio\Panel\Form\Form::make()->fields(['title', 'studio_id']);
            }
        };

        $record = $resource->presentOne($this->heat($studio));

        self::assertSame($studio->getUuid()->toString(), $record['studio_id']);
    }

    public function testAColumnTheEntityCannotAnswerIsRefusedByName(): void
    {
        $resource = new class extends DerivedMovieResource {
            public function table(): TableSchema
            {
                return TableSchema::make()->columns([Column::make('poster_label')]);
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Column "poster_label" on');

        (new RecordPresenter($resource))->rows([$this->heat()]);
    }

    /** With derived rows a value() path is already resolved, so the client must read the key, not the path. */
    public function testValueKeyIsWithheldFromTheClientWhenRowsAreDerived(): void
    {
        $props = $this->renderProps($this->listing(new DerivedMovieResource(), urls: $this->urlGenerator(DerivedMovieResource::class)));

        $studio = array_values(array_filter($props['table']['columns'], static fn (array $c): bool => $c['key'] === 'studio'))[0];

        self::assertArrayNotHasKey('valueKey', $studio);
        self::assertTrue((new DerivedMovieResource())->presentsItself());
        self::assertFalse((new MovieResource())->presentsItself(), 'A resource with its own present() keeps the client-side path.');
    }
}
