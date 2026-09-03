<?php

declare(strict_types=1);

namespace Modufolio\Panel\Tests\Database;

use Modufolio\Panel\Tests\Fixture\BoardOnlyMovieResource;
use Modufolio\Panel\Tests\Case\DoctrineTestCase;
use Modufolio\Panel\Tests\Fixture\Entity\Movie;

/**
 * A board on a resource that declares no form.
 *
 * The property under test is that the two are independent. A board groups
 * records by a field they already have; editing that field by dragging needs
 * no form to edit them *through*. Keying the drag to `canEdit` — which also
 * requires the edit form route — makes every card on such a board immovable
 * while the endpoint behind it works perfectly, which is a failure that shows
 * as nothing happening.
 */
final class BoardViewTest extends DoctrineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['Jaws', true], ['Heat', true], ['Untitled', false]] as [$title, $released]) {
            $movie = new Movie();
            $movie->setTitle($title);
            $movie->setReleased($released);
            self::em()->persist($movie);
        }

        self::em()->flush();
    }

    private function resource(): BoardOnlyMovieResource
    {
        return new BoardOnlyMovieResource();
    }

    public function testAFormlessBoardIsStillDraggable(): void
    {
        $props = $this->renderProps($this->listing($this->resource(), ['view' => 'board']));

        self::assertFalse(
            $props['resource']['canEdit'],
            'No form is declared, so there is no edit route',
        );
        self::assertTrue(
            $props['resource']['canMove'],
            'Cards must still be draggable: a board needs no form',
        );
    }

    public function testTheBoardGroupsRecordsIntoItsDeclaredColumns(): void
    {
        $props = $this->renderProps($this->listing($this->resource(), ['view' => 'board']));

        $columns = $props['board']['columns'];

        // Read as a list, not re-keyed by value: PHP coerces numeric string
        // array keys to integers, so re-keying would assert on '1' having
        // become 1 rather than on what the payload carries.
        self::assertSame(['1', '0'], array_column($columns, 'value'));
        self::assertSame([2, 1], array_column($columns, 'total'));
        self::assertSame(['Jaws', 'Heat'], array_column($columns[0]['cards'], 'title'));
        self::assertSame(['Untitled'], array_column($columns[1]['cards'], 'title'));
    }

    /**
     * Without a position field the board still renders and still moves cards
     * between columns — it just says so, rather than offering an ordering it
     * would lose on reload.
     */
    public function testABoardWithoutAPositionFieldDeclaresItselfUnsortable(): void
    {
        $props = $this->renderProps($this->listing($this->resource(), ['view' => 'board']));

        self::assertFalse($props['board']['view']['sortable']);
    }

    public function testTheTableRemainsTheDefaultAndCarriesNoBoard(): void
    {
        $props = $this->renderProps($this->listing($this->resource()));

        self::assertSame('table', $props['resource']['view']);
        self::assertArrayNotHasKey('board', $props);
    }
}
